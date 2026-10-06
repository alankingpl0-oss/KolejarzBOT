import difflib
import os
import queue
import re
import threading
import time
import tkinter as tk
from tkinter import scrolledtext
import pywikibot
from pywikibot.pagegenerators import PreloadingGenerator

try:
    import winsound
except ImportError:
    winsound = None

# Globalnie skompilowane regexy (nie obciążają CPU w pętli)
PATTERN_TAGS = re.compile(
    r"<(nowiki|pre|code|math).*?>.*?</\1>", re.DOTALL | re.IGNORECASE
)
PATTERN_TEMPLATES = re.compile(r"\{\{[^{}]*\}\}")
PATTERN_SPACES = re.compile(r" {2,}")


def play_beep():
    try:
        if winsound:
            winsound.Beep(1200, 1300)
        else:
            print("\a", end="", flush=True)
    except Exception:
        pass


def analyze_and_remove_spaces(text):
    placeholders = []

    def mask_match(match):
        placeholders.append(match.group(0))
        return f"__PROTECTED_BLOCK_{len(placeholders) - 1}__"

    # 1. Maskowanie bloków
    text_masked = PATTERN_TAGS.sub(mask_match, text)

    # 2. Maskowanie szablonów
    while PATTERN_TEMPLATES.search(text_masked):
        text_masked = PATTERN_TEMPLATES.sub(mask_match, text_masked)

    # 3. Wyszukiwanie spacji
    matches = list(PATTERN_SPACES.finditer(text_masked))
    occurrences_count = len(matches)

    if occurrences_count == 0:
        return text, 0, []

    report_details = []
    lines = text_masked.splitlines()

    for line_num, line in enumerate(lines, start=1):
        if PATTERN_SPACES.search(line):
            clean_line = line
            for i in range(len(placeholders) - 1, -1, -1):
                clean_line = clean_line.replace(
                    f"__PROTECTED_BLOCK_{i}__", f"[BLOK_CHRONIONY_{i}]"
                )
            report_details.append((line_num, clean_line.strip()))

    # 4. Podmiana i przywracanie bloków
    cleaned_masked_text = PATTERN_SPACES.sub(" ", text_masked)
    final_text = cleaned_masked_text
    for i in range(len(placeholders) - 1, -1, -1):
        final_text = final_text.replace(
            f"__PROTECTED_BLOCK_{i}__", placeholders[i]
        )

    return final_text, occurrences_count, report_details


class BotGUI:
    def __init__(self, root):
        self.root = root
        self.root.title("Pywikibot - Szybki Skaner 🐧")
        self.root.geometry("950x800")

        self.task_queue = queue.Queue()
        self.response_queue = queue.Queue()
        self.stop_requested = False

        self.info_label = tk.Label(
            self.root,
            text="Inicjalizacja bota...",
            font=("Arial", 11, "bold"),
            fg="#0056b3",
        )
        self.info_label.pack(pady=10)

        self.text_area = scrolledtext.ScrolledText(
            self.root,
            width=115,
            height=28,
            bg="#1e1e1e",
            fg="#d4d4d4",
            font=("Consolas", 10),
        )
        self.text_area.pack(pady=10, padx=10)

        self.text_area.tag_configure(
            "removed_space",
            foreground="#ff4d4d",
            background="#4a0000",
            underline=True,
        )
        self.text_area.tag_configure(
            "header", foreground="#569cd6", font=("Consolas", 10, "bold")
        )
        self.text_area.tag_configure("line_num", foreground="#b5cea8")

        self.summary_frame = tk.Frame(self.root)
        self.summary_frame.pack(fill=tk.X, padx=15, pady=5)

        self.summary_label = tk.Label(
            self.summary_frame,
            text="Opis zmian (summary):",
            font=("Arial", 10, "bold"),
        )
        self.summary_label.pack(side=tk.LEFT, padx=(0, 5))

        self.summary_entry = tk.Entry(self.summary_frame, font=("Arial", 10))
        self.summary_entry.pack(side=tk.LEFT, fill=tk.X, expand=True)

        self.btn_frame = tk.Frame(self.root)
        self.btn_frame.pack(pady=15)

        self.btn_approve = tk.Button(
            self.btn_frame,
            text="Zatwierdź edycję",
            command=self.approve,
            bg="#28a745",
            fg="white",
            font=("Arial", 10, "bold"),
            state=tk.DISABLED,
        )
        self.btn_approve.pack(side=tk.LEFT, padx=10)

        self.btn_reject = tk.Button(
            self.btn_frame,
            text="Pomiń stronę",
            command=self.reject,
            bg="#dc3545",
            fg="white",
            font=("Arial", 10, "bold"),
            state=tk.DISABLED,
        )
        self.btn_reject.pack(side=tk.LEFT, padx=10)

        self.btn_stop = tk.Button(
            self.btn_frame,
            text="Zatrzymaj bota",
            command=self.stop_script,
            bg="#6c757d",
            fg="white",
            font=("Arial", 10, "bold"),
        )
        self.btn_stop.pack(side=tk.RIGHT, padx=10)

        self.root.after(100, self.process_queue)
        self.root.protocol("WM_DELETE_WINDOW", self.stop_script)

        self.bot_thread = threading.Thread(
            target=run_bot_thread,
            args=(self.task_queue, self.response_queue),
            daemon=True,
        )
        self.bot_thread.start()

    def log(self, message):
        self.text_area.configure(state="normal")
        self.text_area.insert(tk.END, message + "\n")
        self.text_area.see(tk.END)
        self.text_area.configure(state="disabled")

    def set_diff_view(
        self,
        page_title,
        occurrences_count,
        report_details,
        diff_text,
        default_summary,
        speed,
        scanned_count,
    ):
        threading.Thread(target=play_beep, daemon=True).start()

        self.info_label.config(
            text=f"Strona: {page_title} | Spacje: {occurrences_count} \n | Średnia prędkość: {speed:.2f} art./s (skanowano: {scanned_count})"
        )

        self.text_area.configure(state="normal")
        self.text_area.delete("1.0", tk.END)

        self.text_area.insert(
            tk.END,
            f"=== RAPORT WYKRYTYCH MIEJSC ({occurrences_count}) ===\n\n",
            "header",
        )

        # Przetwarzanie linii
        for line_num, line_content in report_details:
            self.text_area.insert(tk.END, f"Linia {line_num}: ", "line_num")

            parts = re.split(r"( {2,})", line_content)
            for part in parts:
                if re.fullmatch(r" {2,}", part):
                    self.text_area.insert(tk.END, part[0])
                    self.text_area.insert(
                        tk.END, part[1:], "removed_space"
                    )
                else:
                    self.text_area.insert(tk.END, part)
            self.text_area.insert(tk.END, "\n")

            tk.END,
            "\n=== PODGLĄD RÓŻNIC (STANDARDOWY DIFF) ===\n\n",
            "header",
        
        self.text_area.insert(tk.END, diff_text)

        self.text_area.see("1.0")
        self.text_area.configure(state="disabled")

        self.summary_entry.delete(0, tk.END)
        self.summary_entry.insert(0, default_summary)

        self.btn_approve.config(state=tk.NORMAL)
        self.btn_reject.config(state=tk.NORMAL)

    def disable_actions(self):
        self.btn_approve.config(state=tk.DISABLED)
        self.btn_reject.config(state=tk.DISABLED)

    def process_queue(self):
        try:
            while True:
                msg_type, data = self.task_queue.get_nowait()
                if msg_type == "LOG":
                    self.log(data)
                elif msg_type == "STATUS":
                    self.info_label.config(text=data)
                elif msg_type == "REVIEW":
                    self.set_diff_view(
                        data["title"],
                        data["count"],
                        data["details"],
                        data["diff"],
                        data["summary"],
                        data["speed"],
                        data["scanned_count"],
                    )
                elif msg_type == "DONE":
                    self.info_label.config(text="Praca bota została zakończona.")
                    self.log("\n--- Zakończono przetwarzanie stron ---")
                    self.disable_actions()
        except queue.Empty:
            pass

        if not self.stop_requested:
            self.root.after(100, self.process_queue)

    def approve(self):
        summary = self.summary_entry.get().strip()
        self.disable_actions()
        self.response_queue.put(
            {"approved": True, "summary": summary, "stop": False}
        )

    def reject(self):
        self.disable_actions()
        self.response_queue.put(
            {"approved": False, "summary": "", "stop": False}
        )

    def stop_script(self):
        self.stop_requested = True
        self.disable_actions()
        self.response_queue.put(
            {"approved": False, "summary": "", "stop": True}
        )
        self.info_label.config(text="Zatrzymywanie bota...")
        self.root.destroy()


EXCLUDED_FILE = "wykonane.txt"


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


def run_bot_thread(task_queue, response_queue):
    site = pywikibot.Site("pl", "wikipedia")
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
                f"Rozpoczynanie skanowania od artykułu: '{start_page}'... Wczytano {len(processed_pages)} wpisów.",
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

    # PreloadingGenerator pobiera po 50 stron w jednym strzale HTTP
    pages_generator = PreloadingGenerator(raw_generator, groupsize=75)

    start_time = time.time()
    scanned_count = 0
    last_ui_update = 0.0

    def mark_page(title):
        processed_pages.add(title)
        writer.add(title)

    for page in pages_generator:
        if page.isRedirectPage():
            continue

        title = page.title()

        if title in processed_pages:
            continue

        scanned_count += 1
        now = time.time()
        elapsed_time = now - start_time

        # Throttling GUI: odświeżamy etykietę maksymalnie co 200 ms, żeby nie przeciążyć pętli zdarzeń
        if now - last_ui_update > 0.2:
            speed = scanned_count / elapsed_time if elapsed_time > 1.0 else 0.0
            task_queue.put(
                (
                    "STATUS",
                    f"Skanowanie: {title} \n Średnia prędkość: {speed:.2f} art./s (skanowano: {scanned_count})",
                )
            )
            last_ui_update = now

        # Próba odczytania tekstu z pamięci podręcznej PreloadingGeneratora
        try:
            original_text = page.text
        except Exception as e:
            task_queue.put(("LOG", f"Błąd pobierania strony {title}: {e}"))
            mark_page(title)
            continue

        new_text, occurrences_count, report_details = analyze_and_remove_spaces(
            original_text
        )

        if occurrences_count > 0:
            speed = scanned_count / elapsed_time if elapsed_time > 0 else 0.0
            diff = difflib.unified_diff(
                original_text.splitlines(),
                new_text.splitlines(),
                fromfile="Oryginał",
                tofile="Propozycja bota",
                lineterm="",
            )
            diff_text = "\n".join(list(diff))
            default_summary = f"KolejarzBOT: Usuwam podwójne spacje (znaleziono: {occurrences_count})"

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
                    },
                )
            )

            response = response_queue.get()

            if response["stop"]:
                task_queue.put(
                    (
                        "LOG",
                        "Przerwano pracę bota na żądanie użytkownika.",
                    )
                )
                mark_page(title)
                writer.flush()
                break

            if response["approved"]:
                try:
                    page.text = new_text
                    page.save(summary=response["summary"], botflag=True)
                    task_queue.put(("LOG", f" ZAPISANO: {title}"))
                except Exception as e:
                    task_queue.put(("LOG", f" Błąd zapisu {title}: {e}"))
                mark_page(title)
            else:
                task_queue.put(("LOG", f" POMINIĘTO: {title}"))
                mark_page(title)
        else:
            mark_page(title)

    writer.flush()
    task_queue.put(("DONE", None))


def main():
    root = tk.Tk()
    app = BotGUI(root)
    root.mainloop()


if __name__ == "__main__":
    main()