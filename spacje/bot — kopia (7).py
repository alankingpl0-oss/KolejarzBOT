import difflib
import io
import math
import os
import queue
import re
import struct
import threading
import time
import tkinter as tk
from datetime import datetime, timezone
from tkinter import messagebox, scrolledtext
import wave
import webbrowser

import pywikibot
from pywikibot.pagegenerators import PreloadingGenerator

try:
    import winsound
except ImportError:
    winsound = None

# Globalnie skompilowane regexy
PATTERN_TAGS = re.compile(
    r"<(nowiki|pre|code|math).*?>.*?</\1>", re.DOTALL | re.IGNORECASE
)
PATTERN_TEMPLATES = re.compile(r"\{\{[^{}]*\}\}")
PATTERN_SPACES = re.compile(r" {2,}")
PATTERN_TRAILING_SPACES = re.compile(r" +(?=\r?$)", re.MULTILINE)

TODO_FILE = "todo.txt"
EXCLUDED_FILE = "wykonane.txt"


# Generowanie przyjemnych dla ucha, miękkich dźwięków powiadomień
def _generate_wav_chime(notes, sample_rate=44100):
    buf = io.BytesIO()
    with wave.open(buf, "wb") as wav:
        wav.setnchannels(1)
        wav.setsampwidth(2)
        wav.setframerate(sample_rate)
        frames = []
        for freq, duration, amp, decay_rate in notes:
            total_samples = int(sample_rate * duration)
            for i in range(total_samples):
                t = i / sample_rate
                attack = min(1.0, i / 250)
                env = attack * math.exp(-decay_rate * t)
                val = (
                    math.sin(2 * math.pi * freq * t) * 0.75
                    + math.sin(4 * math.pi * freq * t) * 0.25
                )
                sample = int(32767 * amp * env * val)
                frames.append(struct.pack("<h", max(-32768, min(32767, sample))))
            wav.writeframes(b"".join(frames))
    return buf.getvalue()


_SOUND_ALERT = None
_SOUND_REMINDER = None

try:
    _SOUND_ALERT = _generate_wav_chime([
        (523.25, 0.09, 0.35, 14),   # C5
        (659.25, 0.09, 0.35, 14),   # E5
        (783.99, 0.32, 0.40, 7),    # G5
    ])
    _SOUND_REMINDER = _generate_wav_chime([
        (440.00, 0.10, 0.30, 12),   # A4
        (587.33, 0.28, 0.35, 8),    # D5
    ])
except Exception:
    pass


def play_beep():
    def _run():
        try:
            if winsound and _SOUND_ALERT:
                winsound.PlaySound(_SOUND_ALERT, winsound.SND_MEMORY)
            elif winsound:
                winsound.Beep(650, 180)
            else:
                print("\a", end="", flush=True)
        except Exception:
            pass

    threading.Thread(target=_run, daemon=True).start()


def play_double_beep():
    def _run():
        try:
            if winsound and _SOUND_REMINDER:
                winsound.PlaySound(_SOUND_REMINDER, winsound.SND_MEMORY)
            elif winsound:
                winsound.Beep(550, 120)
                time.sleep(0.05)
                winsound.Beep(700, 150)
            else:
                print("\a\a", end="", flush=True)
        except Exception:
            pass

    threading.Thread(target=_run, daemon=True).start()


def unmask_full_text(text, placeholders):
    for i in range(len(placeholders) - 1, -1, -1):
        newlines = "\n" * placeholders[i].count("\n")
        text = text.replace(f"__PROTECTED_BLOCK_{i}__{newlines}", placeholders[i])
    return text


def unmask_line_part(text, placeholders):
    for i in range(len(placeholders) - 1, -1, -1):
        text = text.replace(f"__PROTECTED_BLOCK_{i}__", placeholders[i])
    return text


def analyze_and_remove_spaces(text):
    # Fast path: jeśli brak podwójnych spacji i brak spacji przed końcem linii, natychmiast pomiń
    if "  " not in text and not PATTERN_TRAILING_SPACES.search(text):
        return text, 0, [], 0, 0

    placeholders = []

    def mask_match(match):
        idx = len(placeholders)
        val = match.group(0)
        placeholders.append(val)
        newlines = "\n" * val.count("\n")
        return f"__PROTECTED_BLOCK_{idx}__{newlines}"

    # 1. Maskowanie bloków
    text_masked = PATTERN_TAGS.sub(mask_match, text)

    # 2. Maskowanie szablonów
    while PATTERN_TEMPLATES.search(text_masked):
        text_masked = PATTERN_TEMPLATES.sub(mask_match, text_masked)

    # 3. Wyszukiwanie spacji i przygotowanie raportu wizualnego
    report_details = []
    multi_count = 0
    trailing_count = 0
    lines = text_masked.splitlines()

    for line_num, line_masked in enumerate(lines, start=1):
        m_trail = re.search(r" +$", line_masked)
        main_part = line_masked[: m_trail.start()] if m_trail else line_masked
        has_multi = bool(PATTERN_SPACES.search(main_part))

        if m_trail or has_multi:
            chunks = []
            trailing_spaces = m_trail.group(0) if m_trail else ""
            if m_trail:
                trailing_count += 1

            if has_multi:
                parts = re.split(r"( {2,})", main_part)
                for part in parts:
                    if not part:
                        continue
                    if re.fullmatch(r" {2,}", part):
                        multi_count += 1
                        chunks.append((part[0], None))
                        chunks.append((part[1:], "removed_space"))
                    else:
                        unmasked_part = unmask_line_part(part, placeholders)
                        chunks.append((unmasked_part, None))
            else:
                if main_part:
                    unmasked_part = unmask_line_part(main_part, placeholders)
                    chunks.append((unmasked_part, None))

            if trailing_spaces:
                chunks.append((trailing_spaces, "removed_space"))

            report_details.append((line_num, chunks))

    occurrences_count = multi_count + trailing_count
    if occurrences_count == 0:
        return text, 0, [], 0, 0

    # 4. Podmiana i przywracanie bloków
    cleaned_masked_text = PATTERN_TRAILING_SPACES.sub("", text_masked)
    cleaned_masked_text = PATTERN_SPACES.sub(" ", cleaned_masked_text)
    final_text = unmask_full_text(cleaned_masked_text, placeholders)

    return final_text, occurrences_count, report_details, multi_count, trailing_count


def analyze_and_remove_unicode(text, pattern):
    # Fast path: jeśli żaden znak z patternu w ogóle nie występuje w surowym tekście
    if not pattern.search(text):
        return text, 0, []

    placeholders = []

    def mask_match(match):
        idx = len(placeholders)
        val = match.group(0)
        placeholders.append(val)
        newlines = "\n" * val.count("\n")
        return f"__PROTECTED_BLOCK_{idx}__{newlines}"

    # 1. Maskowanie bloków
    text_masked = PATTERN_TAGS.sub(mask_match, text)

    # 2. Maskowanie szablonów
    while PATTERN_TEMPLATES.search(text_masked):
        text_masked = PATTERN_TEMPLATES.sub(mask_match, text_masked)

    # 3. Wyszukiwanie wybranych znaków Unicode
    matches = list(pattern.finditer(text_masked))
    occurrences_count = len(matches)

    if occurrences_count == 0:
        return text, 0, []

    report_details = []
    lines = text_masked.splitlines()

    for line_num, line in enumerate(lines, start=1):
        if pattern.search(line):
            chunks = []
            parts = re.split(f"({pattern.pattern})", line)
            for part in parts:
                if not part:
                    continue
                if pattern.fullmatch(part):
                    hex_val = hex(ord(part)).upper().replace("0X", "U+")
                    chunks.append((f"[{hex_val}]", "removed_unicode"))
                else:
                    unmasked_part = unmask_line_part(part, placeholders)
                    chunks.append((unmasked_part, None))
            report_details.append((line_num, chunks))

    # 4. Podmiana i przywracanie bloków
    cleaned_masked_text = pattern.sub("", text_masked)
    final_text = unmask_full_text(cleaned_masked_text, placeholders)

    return final_text, occurrences_count, report_details


def analyze_and_remove_links(text, page_title):
    """Remove self-links and simplify identical label links.
    Returns (new_text, occurrences_count, report_details).
    """
    if "[[" not in text:
        return text, 0, []
    placeholders = []
    def mask_match(m):
        idx = len(placeholders)
        val = m.group(0)
        placeholders.append(val)
        newlines = "\n" * val.count("\n")
        return f"__PROTECTED_BLOCK_{idx}__{newlines}"
    # Mask tags and templates as in other functions
    text_masked = PATTERN_TAGS.sub(mask_match, text)
    while PATTERN_TEMPLATES.search(text_masked):
        text_masked = PATTERN_TEMPLATES.sub(mask_match, text_masked)
    link_pattern = re.compile(r"\[\[([^|\]]+)(\|([^\]]+))?\]\]")
    report_details = []
    occurrences = 0
    lines = text_masked.splitlines()
    for line_num, line in enumerate(lines, start=1):
        if not link_pattern.search(line):
            continue
        chunks = []
        parts = re.split(r"(\[\[.*?\]\])", line)
        for part in parts:
            if not part:
                continue
            m = link_pattern.fullmatch(part)
            if m:
                target = m.group(1)
                label = m.group(3)
                if target == page_title:
                    # self-link: replace with plain title
                    chunks.append((target, "removed_link"))
                    occurrences += 1
                elif label is not None and target == label:
                    # identical label: simplify
                    chunks.append((f"[[{target}]]", "simplified_link"))
                    occurrences += 1
                else:
                    unmasked = unmask_line_part(part, placeholders)
                    chunks.append((unmasked, None))
            else:
                unmasked = unmask_line_part(part, placeholders)
                chunks.append((unmasked, None))
        if chunks:
            report_details.append((line_num, chunks))
    # Reconstruct text preserving untouched lines
    final_lines = []
    report_iter = iter(report_details)
    current = next(report_iter, None)
    for i, orig_line in enumerate(lines, start=1):
        if current and current[0] == i:
            final_lines.append(''.join(c[0] for c in current[1]))
            current = next(report_iter, None)
        else:
            final_lines.append(unmask_line_part(orig_line, placeholders))
    new_text = "\n".join(final_lines)
    return new_text, occurrences, report_details

class BufferedFileWriter:
    def __init__(self, filename, delay_seconds=3.0):
        self.filename = filename
        self.delay_seconds = delay_seconds
        self.buffer = []
        self.lock = threading.Lock()
        self.timer = None

    def add(self, title):
        with self.lock:
            self.buffer.append(title)
            if self.timer is None:
                self.timer = threading.Timer(self.delay_seconds, self.flush)
                self.timer.start()

    def flush(self):
        with self.lock:
            if self.timer:
                self.timer.cancel()
                self.timer = None

            if not self.buffer:
                return

            try:
                with open(self.filename, "a", encoding="utf-8") as f:
                    for title in self.buffer:
                        f.write(f"{title}\n")
                    self.buffer.clear()
            except Exception as e:
                print(f"Błąd zapisu do pliku {self.filename}: {e}")


def load_processed_pages():
    if not os.path.exists(EXCLUDED_FILE):
        return set()
    with open(EXCLUDED_FILE, "r", encoding="utf-8") as f:
        return set(line.strip() for line in f if line.strip())


def load_todo_pages(site):
    titles = []
    with open(TODO_FILE, "r", encoding="utf-8") as f:
        for line in f:
            t = line.strip()
            if t:
                titles.append(t)

    pages = [pywikibot.Page(site, title) for title in titles]
    return PreloadingGenerator(pages, groupsize=250), len(titles)


def last_edit_monitor_worker(
    task_queue, stop_event, refresh_event, username="KolejarzBOT"
):
    initial_revid = None
    first_check = True
    while not stop_event.is_set():
        try:
            site = pywikibot.Site("pl", "wikipedia")
            target_user = username or site.username() or "KolejarzBOT"
            user = pywikibot.User(site, target_user)
            last_edit = user.last_edit

            if last_edit:
                page, revid, timestamp, comment = last_edit
                title = page.title()

                if first_check:
                    initial_revid = revid
                    first_check = False
                elif revid != initial_revid:
                    initial_revid = revid
                    task_queue.put(("EDIT_SAVED", None))

                if timestamp.tzinfo is None:
                    utc_ts = timestamp.replace(tzinfo=timezone.utc)
                else:
                    utc_ts = timestamp.astimezone(timezone.utc)

                local_ts = utc_ts.astimezone()
                date_str = local_ts.strftime("%Y-%m-%d %H:%M:%S")

                diff_sec = max(
                    0, int((datetime.now(timezone.utc) - utc_ts).total_seconds())
                )
                if diff_sec < 60:
                    ago_str = "przed chwilą"
                elif diff_sec < 120:
                    ago_str = "1 min temu"
                elif diff_sec < 3600:
                    ago_str = f"{diff_sec // 60} min temu"
                elif diff_sec < 86400:
                    hours = diff_sec // 3600
                    mins = (diff_sec % 3600) // 60
                    ago_str = f"{hours} godz. {mins} min temu"
                else:
                    days = diff_sec // 86400
                    ago_str = f"{days} dni temu"

                url = f"https://pl.wikipedia.org/w/index.php?diff={revid}"
                text = f"Ostatnia edycja ({target_user}): {title} • {date_str} ({ago_str})"
                task_queue.put(("LAST_EDIT", {"text": text, "url": url}))
            else:
                text = f"Ostatnia edycja ({target_user}): brak zarejestrowanych edycji"
                task_queue.put(("LAST_EDIT", {"text": text, "url": None}))
        except Exception as e:
            text = f"Ostatnia edycja ({username}): błąd pobierania ({e})"
            task_queue.put(("LAST_EDIT", {"text": text, "url": None}))

        signaled = refresh_event.wait(timeout=60)
        if signaled:
            refresh_event.clear()
            if stop_event.is_set():
                break
            time.sleep(1.0)


def run_bot_thread(task_queue, response_queue, stop_event, config):
    site = pywikibot.Site("pl", "wikipedia")
    task_type = config.get("task_type", "spaces")
    is_auto_mode = config.get("mode", "semi") == "auto"
    source = config.get("source", "allpages")
    unicode_pattern = config.get("unicode_pattern")
    unicode_summary_desc = config.get("unicode_summary_desc", "")

    processed_pages = set()
    writer = None

    if source == "allpages":
        processed_pages = load_processed_pages()
        writer = BufferedFileWriter(EXCLUDED_FILE, delay_seconds=10.0)

        if processed_pages:
            start_page = max(processed_pages)
            raw_generator = site.allpages(
                start=start_page, namespace=0, filterredir=False
            )
            task_queue.put(
                (
                    "LOG",
                    f"Rozpoczynanie skanowania od artykułu: '{start_page}'... Wczytano {len(processed_pages)} wpisów z {EXCLUDED_FILE}.",
                )
            )
        else:
            raw_generator = site.allpages(namespace=0, filterredir=False)
            task_queue.put(
                (
                    "LOG",
                    "Rozpoczynanie skanowania od początku alfabetycznego (A-Z)...",
                )
            )
        pages_generator = PreloadingGenerator(raw_generator, groupsize=250)
        total_count = None
    else:
        try:
            pages_generator, total_count = load_todo_pages(site)
            task_queue.put(
                ("LOG", f"Wczytano {total_count} stron z pliku '{TODO_FILE}'.")
            )
        except Exception as e:
            task_queue.put(("LOG", f"Błąd wczytywania pliku {TODO_FILE}: {e}"))
            task_queue.put(("DONE", None))
            return

    start_time = time.time()
    total_paused_time = 0.0
    scanned_count = 0
    last_ui_update = 0.0

    def mark_page(title):
        if writer:
            processed_pages.add(title)
            writer.add(title)

    if is_auto_mode:
        for page in pages_generator:
            if stop_event.is_set():
                break

            # Obsługa zdarzeń pauzy/stopu w trakcie skanowania
            while True:
                try:
                    msg = response_queue.get_nowait()
                    if msg.get("type") == "TOGGLE_PAUSE":
                        if msg.get("is_paused"):
                            pause_start = time.time()
                            while True:
                                inner_msg = response_queue.get()
                                if inner_msg.get("stop") or stop_event.is_set():
                                    if writer:
                                        writer.flush()
                                    task_queue.put(("DONE", None))
                                    return
                                if (
                                    inner_msg.get("type") == "TOGGLE_PAUSE"
                                    and not inner_msg.get("is_paused")
                                ):
                                    total_paused_time += time.time() - pause_start
                                    break
                    elif msg.get("stop"):
                        if writer:
                            writer.flush()
                        task_queue.put(("DONE", None))
                        return
                except queue.Empty:
                    break

            if page.isRedirectPage():
                continue

            title = page.title()

            if source == "allpages" and title in processed_pages:
                continue

            scanned_count += 1
            now = time.time()
            elapsed_time = (now - start_time) - total_paused_time

            # Throttling GUI: odświeżamy etykietę maksymalnie co 200 ms
            if now - last_ui_update > 0.2:
                speed = (
                    scanned_count / elapsed_time if elapsed_time > 0.5 else 0.0
                )
                count_info = (
                    f"[{scanned_count}/{total_count}]"
                    if total_count
                    else f"(skanowano: {scanned_count})"
                )
                task_queue.put(
                    (
                        "STATUS",
                        f"Skanowanie: {title} {count_info}\n Średnia prędkość: {speed:.2f} art./s",
                    )
                )
                last_ui_update = now

            try:
                original_text = page.text
            except Exception as e:
                task_queue.put(("LOG", f"Błąd pobierania strony {title}: {e}"))
                mark_page(title)
                continue


            if task_type == "unicode":
                new_text, occurrences_count, report_details = (
                    analyze_and_remove_unicode(original_text, unicode_pattern)
                )
                default_summary = (
                    f"KolejarzBOT: Usuwam niewidzialne znaki Unicode"
                    if unicode_summary_desc
                    else f"KolejarzBOT: Usuwam znaki unicode (znaleziono: {occurrences_count})"
                )
            elif task_type == "links":
                new_text, occurrences_count, report_details = (
                    analyze_and_remove_links(original_text, title)
                )
                default_summary = f"KolejarzBOT: Usuwam niepotrzebne linki (znaleziono: {occurrences_count})"
            else:
                (
                    new_text,
                    occurrences_count,
                    report_details,
                    multi_count,
                    trailing_count,
                ) = analyze_and_remove_spaces(original_text)
                if multi_count > 0 and trailing_count > 0:
                    default_summary = f"KolejarzBOT: Usuwam podwójne spacje oraz spacje na końcu linii (znaleziono: {occurrences_count})"
                elif trailing_count > 0:
                    default_summary = f"KolejarzBOT: Usuwam spacje na końcu linii (znaleziono: {trailing_count})"
                else:
                    default_summary = f"KolejarzBOT: Usuwam podwójne spacje (znaleziono: {multi_count})"

            if occurrences_count > 0:
                active_time = (time.time() - start_time) - total_paused_time
                speed = scanned_count / active_time if active_time > 0 else 0.0

                if occurrences_count > 0:
                    try:
                        page.text = new_text
                        page.save(summary=default_summary, botflag=True)
                        task_queue.put(
                            (
                                "LOG",
                                f" [AUTO] ZAPISANO: {title} (usunięto: {occurrences_count})",
                            )
                        )
                        task_queue.put(("TRIGGER_LAST_EDIT_REFRESH", None))
                        task_queue.put(("EDIT_SAVED", None))
                        task_queue.put(("AUTO_SAVED_INC", None))
                    except Exception as e:
                        task_queue.put(("LOG", f" [AUTO] Błąd zapisu {title}: {e}"))
                mark_page(title)

        if writer:
            writer.flush()
        task_queue.put(("DONE", None))
        return

    # === TRYB PÓŁAUTOMATYCZNY (Pipelined Scanner w tle) ===
    candidate_queue = queue.Queue(maxsize=30)
    scanner_paused = threading.Event()
    scanner_paused.set()

    def scanner_producer():
        nonlocal scanned_count, last_ui_update
        try:
            for page in pages_generator:
                if stop_event.is_set():
                    break

                while not scanner_paused.is_set() and not stop_event.is_set():
                    time.sleep(0.1)
                if stop_event.is_set():
                    break

                if page.isRedirectPage():
                    continue

                title = page.title()
                if source == "allpages" and title in processed_pages:
                    continue

                scanned_count += 1
                now = time.time()
                elapsed_time = (now - start_time) - total_paused_time

                if now - last_ui_update > 0.2:
                    speed = (
                        scanned_count / elapsed_time if elapsed_time > 0.5 else 0.0
                    )
                    count_info = (
                        f"[{scanned_count}/{total_count}]"
                        if total_count
                        else f"(skanowano: {scanned_count})"
                    )
                    task_queue.put(
                        (
                            "STATUS",
                            f"Skanowanie: {title} {count_info}\n Średnia prędkość: {speed:.2f} art./s",
                        )
                    )
                    last_ui_update = now

                try:
                    original_text = page.text
                except Exception as e:
                    task_queue.put(("LOG", f"Błąd pobierania strony {title}: {e}"))
                    mark_page(title)
                    continue

                if task_type == "unicode":
                    new_text, occurrences_count, report_details = (
                        analyze_and_remove_unicode(original_text, unicode_pattern)
                    )
                    default_summary = (
                        f"KolejarzBOT: Usuwam niewidzialne znaki Unicode ({unicode_summary_desc})"
                        if unicode_summary_desc
                        else f"KolejarzBOT: Usuwam znaki unicode (znaleziono: {occurrences_count})"
                    )
                else:
                    (
                        new_text,
                        occurrences_count,
                        report_details,
                        multi_count,
                        trailing_count,
                    ) = analyze_and_remove_spaces(original_text)
                    if multi_count > 0 and trailing_count > 0:
                        default_summary = f"KolejarzBOT: Usuwam podwójne spacje oraz spacje na końcu linii (znaleziono: {occurrences_count})"
                    elif trailing_count > 0:
                        default_summary = f"KolejarzBOT: Usuwam spacje na końcu linii (znaleziono: {trailing_count})"
                    else:
                        default_summary = f"KolejarzBOT: Usuwam podwójne spacje (znaleziono: {multi_count})"

                if occurrences_count > 0:
                    diff = difflib.unified_diff(
                        original_text.splitlines(),
                        new_text.splitlines(),
                        fromfile="Oryginał",
                        tofile="Propozycja bota",
                        lineterm="",
                    )
                    diff_text = "\n".join(list(diff))

                    item = {
                        "page": page,
                        "title": title,
                        "count": occurrences_count,
                        "details": report_details,
                        "diff": diff_text,
                        "summary": default_summary,
                        "new_text": new_text,
                    }

                    while not stop_event.is_set():
                        try:
                            candidate_queue.put(item, timeout=0.2)
                            break
                        except queue.Full:
                            continue
                else:
                    mark_page(title)
        except Exception as e:
            task_queue.put(("LOG", f"Błąd skanera w tle: {e}"))
        finally:
            candidate_queue.put(None)

    scanner_thread = threading.Thread(target=scanner_producer, daemon=True)
    scanner_thread.start()

    while not stop_event.is_set():
        try:
            msg = response_queue.get_nowait()
            if msg.get("type") == "TOGGLE_PAUSE":
                if msg.get("is_paused"):
                    scanner_paused.clear()
                    pause_start = time.time()
                    while True:
                        inner_msg = response_queue.get()
                        if inner_msg.get("stop") or stop_event.is_set():
                            stop_event.set()
                            if writer:
                                writer.flush()
                            task_queue.put(("DONE", None))
                            return
                        if (
                            inner_msg.get("type") == "TOGGLE_PAUSE"
                            and not inner_msg.get("is_paused")
                        ):
                            total_paused_time += time.time() - pause_start
                            scanner_paused.set()
                            break
            elif msg.get("stop"):
                stop_event.set()
                if writer:
                    writer.flush()
                task_queue.put(("DONE", None))
                return
        except queue.Empty:
            pass

        try:
            candidate = candidate_queue.get(timeout=0.1)
        except queue.Empty:
            continue

        if candidate is None:
            break

        title = candidate["title"]
        page = candidate["page"]
        occurrences_count = candidate["count"]
        report_details = candidate["details"]
        diff_text = candidate["diff"]
        default_summary = candidate["summary"]
        new_text = candidate["new_text"]

        active_time = (time.time() - start_time) - total_paused_time
        speed = scanned_count / active_time if active_time > 0 else 0.0

        task_queue.put(
            (
                "REVIEW",
                {
                    "title": title,
                    "count": occurrences_count,
                    "details": report_details,
                    "diff": diff_text,
                    "summary": default_summary,
                    "speed": speed,
                    "scanned_count": scanned_count,
                    "task_type": task_type,
                },
            )
        )

        pause_start = time.time()
        response = None
        while True:
            resp = response_queue.get()
            if resp.get("type") == "TOGGLE_PAUSE":
                if resp.get("is_paused"):
                    scanner_paused.clear()
                else:
                    scanner_paused.set()
                continue
            response = resp
            break

        if response.get("stop") or stop_event.is_set():
            task_queue.put(("LOG", "Przerwano pracę bota na żądanie użytkownika."))
            mark_page(title)
            stop_event.set()
            if writer:
                writer.flush()
            total_paused_time += time.time() - pause_start
            break

        if response.get("approved"):
            try:
                page.text = new_text
                page.save(
                    summary=response.get("summary", default_summary),
                    botflag=True,
                )
                task_queue.put(("LOG", f" ZAPISANO: {title}"))
                task_queue.put(("TRIGGER_LAST_EDIT_REFRESH", None))
                task_queue.put(("EDIT_SAVED", None))
            except Exception as e:
                task_queue.put(("LOG", f" Błąd zapisu {title}: {e}"))
            mark_page(title)
        else:
            task_queue.put(("LOG", f" POMINIĘTO: {title}"))
            mark_page(title)

        total_paused_time += time.time() - pause_start

    stop_event.set()
    if writer:
        writer.flush()
    task_queue.put(("DONE", None))


class BotGUI:
    def __init__(self, root):
        self.root = root
        self.root.title("Pywikibot - Skaner KolejarzBOT 🐧")
        self.root.geometry("1020x920")

        self.task_queue = queue.Queue()
        self.response_queue = queue.Queue()
        self.bot_thread = None
        self.stop_requested = False
        self.is_running = False
        self.is_paused = False
        self.stop_event = threading.Event()
        self.bot_stop_event = threading.Event()
        self.refresh_event = threading.Event()
        self.last_edit_url = None
        self.last_edit_session_time = None
        self.last_beep_time = None
        self.current_page_title = None

        # Statystyki sesji
        self.approved_count = 0
        self.rejected_count = 0

        # --- RAMKA KONFIGURACJI ---
        self.settings_frame = tk.LabelFrame(
            self.root,
            text="⚙ Konfiguracja i parametry skanera",
            font=("Arial", 9, "bold"),
            fg="#0056b3",
            padx=10,
            pady=6,
        )
        self.settings_frame.pack(fill=tk.X, padx=15, pady=(8, 4))

        # Wiersz 1: Zadanie, Tryb, Źródło
        row1_frame = tk.Frame(self.settings_frame)
        row1_frame.pack(fill=tk.X, pady=2)

        # Zadanie
        tk.Label(row1_frame, text="Zadanie:", font=("Arial", 9, "bold")).pack(
            side=tk.LEFT, padx=(0, 4)
        )
        self.task_var = tk.StringVar(value="spaces")
        self.rb_spaces = tk.Radiobutton(
            row1_frame,
            text="Spacje",
            variable=self.task_var,
            value="spaces",
            command=self.on_task_changed,
        )
        self.rb_spaces.pack(side=tk.LEFT, padx=3)
        self.rb_unicode = tk.Radiobutton(
            row1_frame,
            text="Znaki Unicode",
            variable=self.task_var,
            value="unicode",
            command=self.on_task_changed,
        )
        self.rb_unicode.pack(side=tk.LEFT, padx=3)
        self.rb_links = tk.Radiobutton(
            row1_frame,
            text="Linki",
            variable=self.task_var,
            value="links",
            command=self.on_task_changed,
        )
        self.rb_links.pack(side=tk.LEFT, padx=3)

        tk.Label(row1_frame, text=" |  Tryb:", font=("Arial", 9, "bold")).pack(
            side=tk.LEFT, padx=(8, 4)
        )
        self.mode_var = tk.StringVar(value="semi")
        self.rb_semi = tk.Radiobutton(
            row1_frame,
            text="Półautomatyczny",
            variable=self.mode_var,
            value="semi",
        )
        self.rb_semi.pack(side=tk.LEFT, padx=3)
        self.rb_auto = tk.Radiobutton(
            row1_frame,
            text="Automatyczny",
            variable=self.mode_var,
            value="auto",
        )
        self.rb_auto.pack(side=tk.LEFT, padx=3)

        tk.Label(row1_frame, text=" |  Źródło:", font=("Arial", 9, "bold")).pack(
            side=tk.LEFT, padx=(8, 4)
        )
        self.source_var = tk.StringVar(value="allpages")
        self.rb_allpages = tk.Radiobutton(
            row1_frame,
            text="A-Z (wykonane.txt)",
            variable=self.source_var,
            value="allpages",
        )
        self.rb_allpages.pack(side=tk.LEFT, padx=3)
        self.rb_todo = tk.Radiobutton(
            row1_frame,
            text="todo.txt",
            variable=self.source_var,
            value="todo",
        )
        self.rb_todo.pack(side=tk.LEFT, padx=3)

        # Wiersz 2: Kody Unicode i przycisk start
        row2_frame = tk.Frame(self.settings_frame)
        row2_frame.pack(fill=tk.X, pady=(5, 2))

        self.unicode_label = tk.Label(
            row2_frame, text="Kody Unicode (hex):", font=("Arial", 9)
        )
        self.unicode_label.pack(side=tk.LEFT, padx=(0, 4))

        self.unicode_entry = tk.Entry(row2_frame, width=28, font=("Arial", 9))
        self.unicode_entry.insert(0, "feff, 200e, 200b")
        self.unicode_entry.pack(side=tk.LEFT, padx=(0, 10))

        self.btn_start = tk.Button(
            row2_frame,
            text="▶ Uruchom bota",
            command=self.start_bot,
            bg="#0056b3",
            fg="white",
            font=("Arial", 9, "bold"),
            padx=14,
            pady=2,
        )
        self.btn_start.pack(side=tk.RIGHT, padx=5)

        self.on_task_changed()

        self.info_label = tk.Label(
            self.root,
            text="Wybierz parametry i kliknij 'Uruchom bota'...",
            font=("Arial", 11, "bold"),
            fg="#0056b3",
        )
        self.info_label.pack(pady=(6, 3))

        self.last_edit_frame = tk.Frame(
            self.root, bg="#eef2f7", bd=1, relief=tk.SOLID
        )
        self.last_edit_frame.pack(fill=tk.X, padx=15, pady=(0, 4))

        self.last_edit_label = tk.Label(
            self.last_edit_frame,
            text="Ostatnia edycja (KolejarzBOT): sprawdzanie...",
            font=("Arial", 9),
            fg="#212529",
            bg="#eef2f7",
            padx=8,
            pady=4,
            cursor="hand2",
        )
        self.last_edit_label.pack(side=tk.LEFT, fill=tk.X, expand=True)
        self.last_edit_label.bind("<Button-1>", self.open_last_edit_url)
        self.last_edit_label.bind(
            "<Enter>",
            lambda e: self.last_edit_label.config(
                fg="#0056b3" if self.last_edit_url else "#212529"
            ),
        )
        self.last_edit_label.bind(
            "<Leave>",
            lambda e: self.last_edit_label.config(fg="#212529"),
        )

        self.btn_refresh_edit = tk.Button(
            self.last_edit_frame,
            text="⟳ Odśwież (F5)",
            font=("Arial", 8),
            bg="#ffffff",
            fg="#333333",
            relief=tk.GROOVE,
            padx=6,
            pady=1,
            command=self.manual_refresh_last_edit,
        )
        self.btn_refresh_edit.pack(side=tk.RIGHT, padx=5, pady=2)

        # Pasek statystyk sesji
        self.stats_frame = tk.Frame(self.root)
        self.stats_frame.pack(fill=tk.X, padx=15, pady=(0, 4))
        self.stats_label = tk.Label(
            self.stats_frame,
            text="Zapisano w tej sesji: 0 | Pominięto: 0",
            font=("Arial", 9, "italic"),
            fg="#495057",
        )
        self.stats_label.pack(side=tk.LEFT)

        self.text_area = scrolledtext.ScrolledText(
            self.root,
            width=120,
            height=26,
            bg="#1e1e1e",
            fg="#d4d4d4",
            font=("Consolas", 10),
        )
        self.text_area.pack(pady=4, padx=15, fill=tk.BOTH, expand=True)

        self.text_area.tag_configure(
            "removed_space",
            foreground="#ffffff",
            background="#cc0000",
            underline=True,
        )
        self.text_area.tag_configure(
            "removed_unicode",
            foreground="#ffffff",
            background="#cc0000",
            font=("Consolas", 10, "bold"),
        )
        self.text_area.tag_configure(
            "header", foreground="#569cd6", font=("Consolas", 10, "bold")
        )
        self.text_area.tag_configure("line_num", foreground="#b5cea8")
        self.text_area.tag_configure("diff_plus", foreground="#4ec9b0")
        self.text_area.tag_configure("diff_minus", foreground="#f44747")

        self.summary_frame = tk.Frame(self.root)
        self.summary_frame.pack(fill=tk.X, padx=15, pady=4)

        self.summary_label = tk.Label(
            self.summary_frame,
            text="Opis zmian (summary):",
            font=("Arial", 10, "bold"),
        )
        self.summary_label.pack(side=tk.LEFT, padx=(0, 5))

        self.summary_entry = tk.Entry(self.summary_frame, font=("Arial", 10))
        self.summary_entry.pack(side=tk.LEFT, fill=tk.X, expand=True)

        self.btn_frame = tk.Frame(self.root)
        self.btn_frame.pack(pady=8)

        self.btn_approve = tk.Button(
            self.btn_frame,
            text="✔ Zatwierdź [Enter / Y]",
            command=self.approve,
            bg="#28a745",
            fg="white",
            font=("Arial", 10, "bold"),
            state=tk.DISABLED,
            padx=10,
            pady=4,
        )
        self.btn_approve.pack(side=tk.LEFT, padx=6)

        self.btn_reject = tk.Button(
            self.btn_frame,
            text="✖ Pomiń [Spacja / N / Esc]",
            command=self.reject,
            bg="#dc3545",
            fg="white",
            font=("Arial", 10, "bold"),
            state=tk.DISABLED,
            padx=10,
            pady=4,
        )
        self.btn_reject.pack(side=tk.LEFT, padx=6)

        self.btn_open_browser = tk.Button(
            self.btn_frame,
            text="🌐 Otwórz artykuł [O]",
            command=self.open_current_page_browser,
            bg="#17a2b8",
            fg="white",
            font=("Arial", 10, "bold"),
            state=tk.DISABLED,
            padx=10,
            pady=4,
        )
        self.btn_open_browser.pack(side=tk.LEFT, padx=6)

        self.btn_pause = tk.Button(
            self.btn_frame,
            text="⏸ Pauza [P]",
            command=self.toggle_pause,
            bg="#ffc107",
            fg="#212529",
            font=("Arial", 10, "bold"),
            state=tk.DISABLED,
            padx=10,
            pady=4,
        )
        self.btn_pause.pack(side=tk.LEFT, padx=6)

        self.btn_stop = tk.Button(
            self.btn_frame,
            text="⏹ Zatrzymaj bota [Q]",
            command=self.stop_bot_execution,
            bg="#6c757d",
            fg="white",
            font=("Arial", 10, "bold"),
            state=tk.DISABLED,
            padx=10,
            pady=4,
        )
        self.btn_stop.pack(side=tk.RIGHT, padx=6)

        self.setup_keybindings()

        self.root.after(100, self.process_queue)
        self.root.protocol("WM_DELETE_WINDOW", self.on_close_window)

        self.last_edit_thread = threading.Thread(
            target=last_edit_monitor_worker,
            args=(
                self.task_queue,
                self.stop_event,
                self.refresh_event,
                "KolejarzBOT",
            ),
            daemon=True,
        )
        self.last_edit_thread.start()

    def on_task_changed(self):
        is_unicode = self.task_var.get() == "unicode"
        state = tk.NORMAL if is_unicode else tk.DISABLED
        self.unicode_entry.config(state=state)
        self.unicode_label.config(fg="#000000" if is_unicode else "#888888")

    def setup_keybindings(self):
        self.root.bind("<F5>", lambda e: self.manual_refresh_last_edit())
        self.root.bind("<KeyPress-p>", lambda e: self.toggle_pause())
        self.root.bind("<KeyPress-P>", lambda e: self.toggle_pause())
        self.root.bind("<KeyPress-q>", lambda e: self.stop_bot_execution())
        self.root.bind("<KeyPress-Q>", lambda e: self.stop_bot_execution())

        def on_key(event):
            if event.widget == self.summary_entry:
                if event.keysym in ("Return", "KP_Enter"):
                    if str(self.btn_approve["state"]) == tk.NORMAL:
                        self.approve()
                return

            if event.keysym in ("Return", "KP_Enter") or event.char in (
                "y",
                "Y",
            ):
                if str(self.btn_approve["state"]) == tk.NORMAL:
                    self.approve()
            elif event.keysym in ("space", "Escape") or event.char in ("n", "N"):
                if str(self.btn_reject["state"]) == tk.NORMAL:
                    self.reject()
            elif event.char in ("o", "O"):
                if str(self.btn_open_browser["state"]) == tk.NORMAL:
                    self.open_current_page_browser()

        self.root.bind("<Key>", on_key)

    def set_config_ui_state(self, enabled):
        state = tk.NORMAL if enabled else tk.DISABLED
        self.rb_spaces.config(state=state)
        self.rb_unicode.config(state=state)
        self.rb_semi.config(state=state)
        self.rb_auto.config(state=state)
        self.rb_allpages.config(state=state)
        self.rb_todo.config(state=state)
        if enabled:
            self.on_task_changed()
            self.btn_start.config(state=tk.NORMAL)
            self.btn_pause.config(state=tk.DISABLED)
            self.btn_stop.config(state=tk.DISABLED)
        else:
            self.unicode_entry.config(state=tk.DISABLED)
            self.btn_start.config(state=tk.DISABLED)
            self.btn_pause.config(state=tk.NORMAL)
            self.btn_stop.config(state=tk.NORMAL)

    def start_bot(self):
        task_type = self.task_var.get()
        mode = self.mode_var.get()
        source = self.source_var.get()

        if source == "todo" and not os.path.exists(TODO_FILE):
            messagebox.showerror(
                "Błąd", f"Brak pliku '{TODO_FILE}' z listą artykułów!"
            )
            return

        unicode_pattern = None
        unicode_summary_desc = ""

        if task_type == "unicode":
            user_input = self.unicode_entry.get()
            raw_codes = re.findall(r"[0-9a-fA-F]+", user_input)
            valid_chars = []
            for code in raw_codes:
                try:
                    valid_chars.append(chr(int(code, 16)))
                except ValueError:
                    pass

            if not valid_chars:
                messagebox.showerror(
                    "Błąd", "Nie podano prawidłowych kodów Unicode (hex)."
                )
                return

            pattern_str = "[" + "".join(re.escape(c) for c in valid_chars) + "]"
            unicode_pattern = re.compile(pattern_str)
            unicode_summary_desc = ", ".join(
                f"U+{ord(c):04X}" for c in valid_chars
            )

        self.is_running = True
        self.is_paused = False
        self.bot_stop_event.clear()
        self.set_config_ui_state(False)

        config = {
            "task_type": task_type,
            "mode": mode,
            "source": source,
            "unicode_pattern": unicode_pattern,
            "unicode_summary_desc": unicode_summary_desc,
        }

        mode_name = "Automatycznym" if mode == "auto" else "Półautomatycznym"
        task_name = "Znaki Unicode" if task_type == "unicode" else "Spacje"
        self.log(
            f"--- Uruchomiono: zadanie={task_name}, tryb={mode_name}, źródło={source} ---"
        )
        if mode == "auto":
            self.log(
                "Tryb AUTO uaktywniony: zmiany będą zapisywane automatycznie."
            )

        self.bot_thread = threading.Thread(
            target=run_bot_thread,
            args=(
                self.task_queue,
                self.response_queue,
                self.bot_stop_event,
                config,
            ),
            daemon=True,
        )
        self.bot_thread.start()

    def toggle_pause(self):
        if not self.is_running:
            return
        self.is_paused = not self.is_paused
        if self.is_paused:
            self.btn_pause.config(
                text="▶ Wznów [P]", bg="#28a745", fg="white"
            )
            self.info_label.config(
                text="PAUZA - Bot wstrzymany. Wciśnij [P], aby wznowić."
            )
        else:
            self.btn_pause.config(
                text="⏸ Pauza [P]", bg="#ffc107", fg="#212529"
            )
            self.info_label.config(text="Wznowiono pracę bota...")
        self.response_queue.put(
            {"type": "TOGGLE_PAUSE", "is_paused": self.is_paused}
        )

    def open_current_page_browser(self):
        if self.current_page_title:
            url = f"https://pl.wikipedia.org/wiki/{self.current_page_title.replace(' ', '_')}"
            webbrowser.open(url)

    def log(self, message):
        self.text_area.configure(state="normal")
        self.text_area.insert(tk.END, message + "\n")
        self.text_area.see(tk.END)
        self.text_area.configure(state="disabled")

    def set_diff_view(self, data):
        self.current_page_title = data["title"]
        page_title = data["title"]
        occurrences_count = data["count"]
        report_details = data["details"]
        diff_text = data["diff"]
        default_summary = data["summary"]
        speed = data["speed"]
        scanned_count = data["scanned_count"]
        task_type = data.get("task_type", "spaces")

        threading.Thread(target=play_beep, daemon=True).start()

        label_item = "Znaków" if task_type == "unicode" else "Spacji"
        self.info_label.config(
            text=f"Strona: {page_title} | {label_item}: {occurrences_count} \n | Średnia prędkość: {speed:.2f} art./s (skanowano: {scanned_count})"
        )

        self.text_area.configure(state="normal")
        self.text_area.delete("1.0", tk.END)

        self.text_area.insert(
            tk.END,
            f"=== RAPORT WYKRYTYCH MIEJSC ({occurrences_count}) ===\n\n",
            "header",
        )

        # Przetwarzanie linii na podstawie gotowych fragmentów
        for line_num, chunks in report_details:
            self.text_area.insert(tk.END, f"Linia {line_num}: ", "line_num")
            for text_segment, tag in chunks:
                if tag:
                    self.text_area.insert(tk.END, text_segment, tag)
                else:
                    self.text_area.insert(tk.END, text_segment)
            self.text_area.insert(tk.END, "\n")

        self.text_area.insert(
            tk.END,
            "\n=== PODGLĄD RÓŻNIC (STANDARDOWY DIFF) ===\n\n",
            "header",
        )
        for line in diff_text.splitlines():
            if line.startswith("+") and not line.startswith("+++"):
                self.text_area.insert(tk.END, line + "\n", "diff_plus")
            elif line.startswith("-") and not line.startswith("---"):
                self.text_area.insert(tk.END, line + "\n", "diff_minus")
            elif line.startswith("@@"):
                self.text_area.insert(tk.END, line + "\n", "header")
            else:
                self.text_area.insert(tk.END, line + "\n")

        self.text_area.see("1.0")
        self.text_area.configure(state="disabled")

        self.summary_entry.delete(0, tk.END)
        self.summary_entry.insert(0, default_summary)

        self.btn_approve.config(state=tk.NORMAL)
        self.btn_reject.config(state=tk.NORMAL)
        self.btn_open_browser.config(state=tk.NORMAL)

    def disable_actions(self):
        self.btn_approve.config(state=tk.DISABLED)
        self.btn_reject.config(state=tk.DISABLED)
        self.btn_open_browser.config(state=tk.DISABLED)

    def update_stats_display(self):
        self.stats_label.config(
            text=f"Zapisano w tej sesji: {self.approved_count} | Pominięto: {self.rejected_count}"
        )

    def process_queue(self):
        try:
            while True:
                msg_type, data = self.task_queue.get_nowait()
                if msg_type == "LOG":
                    self.log(data)
                elif msg_type == "STATUS":
                    if not self.is_paused:
                        self.info_label.config(text=data)
                elif msg_type == "REVIEW":
                    self.set_diff_view(data)
                elif msg_type == "AUTO_SAVED_INC":
                    self.approved_count += 1
                    self.update_stats_display()
                elif msg_type == "DONE":
                    self.is_running = False
                    self.info_label.config(
                        text="Praca bota została zakończona."
                    )
                    self.log("\n--- Zakończono przetwarzanie stron ---")
                    self.disable_actions()
                    self.set_config_ui_state(True)
                    self.last_edit_session_time = None
                elif msg_type == "LAST_EDIT":
                    self.update_last_edit_ui(data)
                elif msg_type == "TRIGGER_LAST_EDIT_REFRESH":
                    self.refresh_event.set()
                elif msg_type == "EDIT_SAVED":
                    now = time.time()
                    self.last_edit_session_time = now
                    self.last_beep_time = now
        except queue.Empty:
            pass

        self.check_inactivity_beep()

        if not self.stop_requested:
            self.root.after(100, self.process_queue)

    def check_inactivity_beep(self):
        if (
            self.stop_requested
            or self.is_paused
            or not self.is_running
            or self.last_edit_session_time is None
        ):
            return

        now = time.time()
        elapsed = now - self.last_edit_session_time

        if elapsed >= 120:
            if self.last_beep_time is None or (now - self.last_beep_time) >= 60:
                self.last_beep_time = now
                play_double_beep()

    def manual_refresh_last_edit(self):
        self.last_edit_label.config(
            text="Ostatnia edycja (KolejarzBOT): sprawdzanie..."
        )
        self.refresh_event.set()

    def open_last_edit_url(self, event=None):
        if self.last_edit_url:
            webbrowser.open(self.last_edit_url)

    def update_last_edit_ui(self, data):
        self.last_edit_label.config(text=data.get("text", ""))
        self.last_edit_url = data.get("url")
        if self.last_edit_url:
            self.last_edit_label.config(cursor="hand2")
        else:
            self.last_edit_label.config(cursor="")

    def approve(self):
        if str(self.btn_approve["state"]) == tk.DISABLED:
            return
        summary = self.summary_entry.get().strip()
        self.disable_actions()
        self.approved_count += 1
        self.update_stats_display()
        self.response_queue.put(
            {"approved": True, "summary": summary, "stop": False}
        )

    def reject(self):
        if str(self.btn_reject["state"]) == tk.DISABLED:
            return
        self.disable_actions()
        self.rejected_count += 1
        self.update_stats_display()
        self.response_queue.put(
            {"approved": False, "summary": "", "stop": False}
        )

    def stop_bot_execution(self):
        if not self.is_running:
            return
        self.bot_stop_event.set()
        self.disable_actions()
        self.response_queue.put(
            {"approved": False, "summary": "", "stop": True}
        )
        self.info_label.config(text="Zatrzymywanie bota...")
        self.log("[!] Wysłano sygnał zatrzymania do wątku bota...")

    def on_close_window(self):
        self.stop_requested = True
        self.last_edit_session_time = None
        self.stop_event.set()
        self.bot_stop_event.set()
        self.refresh_event.set()
        self.disable_actions()
        self.response_queue.put(
            {"approved": False, "summary": "", "stop": True}
        )
        self.root.destroy()


def main():
    root = tk.Tk()
    app = BotGUI(root)
    root.mainloop()


if __name__ == "__main__":
    main()