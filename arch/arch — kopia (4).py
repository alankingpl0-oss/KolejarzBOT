# Skrypt archiwizujący strony dyskusji na pl.wikipedia.org
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

def archive_talk_page(page):
    """Archiwizuje podaną stronę dyskusji (obiekt pywikibot.Page)."""
    try:
        page.get(force=True)
    except pywikibot.exceptions.NoPageError:
        pywikibot.output(f"Strona [[{page.title()}]] nie istnieje.")
        return
    except pywikibot.exceptions.IsRedirectPageError:
        page = page.getRedirectTarget()
        page.get(force=True)

    text = page.text
    config, config_match = parse_config(text)

    if not config:
        pywikibot.output(f"Nie znaleziono szablonu konfiguracji na [[{page.title()}]].")
        return

    try:
        licznik = int(config.get('licznik', 1))
        max_watki = int(config.get('max wątki', 10))
        minimum = int(config.get('minimum', 5))
        archive_pattern = config.get('archiwum', '/Archiwum $licznik')
    except ValueError:
        pywikibot.output(f"Błąd parametrów na [[{page.title()}]]: 'licznik', 'max wątki' i 'minimum' muszą być liczbami.")
        return

    sections = re.split(r'\n(?===)', text)
    header = sections[0]
    threads = sections[1:]

    # Warunek bramkowy: sprawdzamy max wątki
    if len(threads) <= max_watki:
        pywikibot.output(f"[[{page.title()}]]: Brak potrzeby archiwizacji ({len(threads)} wątków <= max {max_watki}).")
        return

    num_to_archive = len(threads) - minimum
    if num_to_archive <= 0:
        pywikibot.output(f"[[{page.title()}]]: Parametr 'minimum' ({minimum}) jest większy/równy liczbie wątków ({len(threads)}). Pomijam.")
        return

    threads_to_archive = threads[:num_to_archive]
    threads_to_keep = threads[num_to_archive:]

    subpage_name = archive_pattern.replace('$licznik', str(licznik)).strip()
    if subpage_name.startswith('/'):
        archive_title = f"{page.title()}{subpage_name}"
    else:
        archive_title = subpage_name

    archive_page = pywikibot.Page(page.site, archive_title)

    # 1. Tworzenie/aktualizacja strony archiwum
    content_to_append = "\n" + "\n".join(threads_to_archive)
    if archive_page.exists():
        archive_page.text += content_to_append
    else:
        archive_page.text = f"{{{{archiwum}}}}\n{content_to_append.strip()}"

    try:
        archive_page.save(summary=f"Bot: Archiwizacja {num_to_archive} wątków ze strony [[{page.title()}]]")
    except pywikibot.exceptions.AbuseFilterDisallowedError as e:
        pywikibot.output(f"Blokada AbuseFilter przy zapisie archiwum [[{archive_title}]]: {e}")
        return

    # 2. Aktualizacja strony głównej dyskusji
    new_header = increment_counter_in_text(header, licznik, licznik + 1)
    new_page_text = new_header + "\n" + "\n".join(threads_to_keep)
    page.text = new_page_text

    try:
        page.save(summary=f"Bot: Zarchiwizowane wątki ({num_to_archive} szt.) do [[{archive_title}]], zwiększono licznik do {licznik + 1}.")
    except pywikibot.exceptions.AbuseFilterDisallowedError:
        pywikibot.output(f"BŁĄD: Zapis zarchiwizowanej strony [[{page.title()}]] zablokowany przez AbuseFilter.")

def main():
    site = pywikibot.Site('pl', 'wikipedia')
    template_page = pywikibot.Page(site, CONFIG_TEMPLATE)

    pywikibot.output(f"Szukanie stron zawierających szablon [[{CONFIG_TEMPLATE}]]...")
    
    # embeddedin() zwraca generator ze wszystkimi stronami, które wywołują ten szablon
    pages = template_page.embeddedin(filter_redirects=False)

    processed_count = 0
    for page in pages:
        pywikibot.output(f"\nPrzetwarzanie: [[{page.title()}]]")
        archive_talk_page(page)
        processed_count += 1

    pywikibot.output(f"\nKoniec pracy. Sprawdzono stron: {processed_count}.")

if __name__ == "__main__":
    main()