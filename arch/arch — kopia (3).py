#/* Blokowy komentarz konfiguracyjny bota archiwizującego */
import re
import pywikibot

CONFIG_TEMPLATE = "Wikipedysta:KolejarzBOT/archiwum/config"

def parse_config(text):
    """Wyciąga parametry z szablonu konfiguracji."""
    pattern = r'\{\{\s*' + re.escape(CONFIG_TEMPLATE) + r'(?P<params>[^}]*)\}\}'
    match = re.search(pattern, text, re.IGNORECASE | re.DOTALL)
    if not match:
        return None, None

    params_raw = match.group('params')
    config = {}
    
    for line in params_raw.split('\n'):
        if '=' in line:
            key, value = line.split('=', 1)
            key = key.strip().lstrip('|').strip()
            value = value.strip()
            config[key] = value

    return config, match

def increment_counter_in_text(text, current_val, new_val):
    """Zwiększa wartość parametru 'licznik' w szablonie konfiguracji o 1."""
    pattern = r'(\|\s*licznik\s*=\s*)' + re.escape(str(current_val))
    replacement = r'\g<1>' + str(new_val)
    return re.sub(pattern, replacement, text, count=1)

def archive_talk_page(page_title):
    site = pywikibot.Site('pl', 'wikipedia')
    page = pywikibot.Page(site, page_title)

    try:
        page.get(force=True)
    except pywikibot.exceptions.NoPageError:
        pywikibot.output(f"Strona {page_title} nie istnieje na pl.wikipedia.org.")
        return
    except pywikibot.exceptions.IsRedirectPageError:
        page = page.getRedirectTarget()
        page.get(force=True)

    text = page.text
    config, config_match = parse_config(text)

    if not config:
        pywikibot.output("Nie znaleziono szablonu konfiguracji archiwizacji.")
        return

    try:
        licznik = int(config.get('licznik', 1))
        max_watki = int(config.get('max wątki', 10))
        minimum = int(config.get('minimum', 5))
        archive_pattern = config.get('archiwum', '/Archiwum $licznik')
    except ValueError:
        pywikibot.output("Błąd: Parametry 'licznik', 'max wątki' lub 'minimum' muszą być liczbami.")
        return

    sections = re.split(r'\n(?===)', text)
    header = sections[0]
    threads = sections[1:]

    if len(threads) <= max_watki:
        pywikibot.output(f"Brak potrzeby archiwizacji. Liczba wątków ({len(threads)}) <= max wątki ({max_watki}).")
        return

    num_to_archive = len(threads) - minimum
    threads_to_archive = threads[:num_to_archive]
    threads_to_keep = threads[num_to_archive:]

    subpage_name = archive_pattern.replace('$licznik', str(licznik)).strip()
    if subpage_name.startswith('/'):
        archive_title = f"{page.title()}{subpage_name}"
    else:
        archive_title = subpage_name

    archive_page = pywikibot.Page(site, archive_title)

    # 1. Tworzenie/aktualizacja strony archiwum
    content_to_append = "\n" + "\n".join(threads_to_archive)
    if archive_page.exists():
        archive_page.text += content_to_append
    else:
        archive_page.text = f"{{{{archiwum}}}}\n{content_to_append.strip()}"

    try:
        archive_page.save(summary=f"Bot: Archiwizacja {num_to_archive} wątków ze strony [[{page.title()}]]")
    except pywikibot.exceptions.AbuseFilterDisallowedError as e:
        pywikibot.output(f"Blokada przy tworzeniu archiwum przez AbuseFilter: {e}")
        return

    # 2. Aktualizacja i czyszczenie strony głównej dyskusji
    new_header = increment_counter_in_text(header, licznik, licznik + 1)
    new_page_text = new_header + "\n" + "\n".join(threads_to_keep)
    page.text = new_page_text

    try:
        page.save(summary=f"Bot: Usunięto zarchiwizowane wątki ({num_to_archive} szt.) do [[{archive_title}]], zwiększono licznik do {licznik + 1}.")
    except pywikibot.exceptions.AbuseFilterDisallowedError:
        pywikibot.output("BŁĄD: Zapis zarchiwizowanych wątków na stronie głównej został zablokowany przez Filtr Nadużyć (AbuseFilter) ze względu na brak wymaganych uprawnień konta botowego (brak statusu autoconfirmed / flagi bota).")

if __name__ == "__main__":
    target_page = "Dyskusja wikipedysty:Kolejarz999"
    archive_talk_page(target_page)