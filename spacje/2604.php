<?php
session_set_cookie_params(0);
session_start();


if (isset($_SESSION['user'])) {
    $dbFile = 'users.json';
    $db = json_decode(file_get_contents($dbFile), true);
    $db[$_SESSION['user']]['last_active'] = time(); // Zapisujemy aktualny czas
    file_put_contents($dbFile, json_encode($db));
}

// --- KONFIGURACJA HASŁA DO STRONY (GATEKEEPER) ---
$SITE_PASSWORD = "potwór";

if (isset($_POST['gate_pass'])) {
    if ($_POST['gate_pass'] === $SITE_PASSWORD) {
        $_SESSION['site_access'] = true;
    } else {
        sleep(1); 
        $error = "Błędne hasło dostępu.";
    }
}

if (!isset($_SESSION['site_access'])) {
    ?>
    <!DOCTYPE html>
    <html lang="pl">
    <head>
        <meta charset="UTF-8">
        <title>Dostęp Zabroniony</title>
        <style>
            body { background: #000; color: #0f0; font-family: monospace; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            input { background: #111; border: 1px solid #0f0; color: #0f0; padding: 10px; width: 300px; outline: none; }
            button { background: #0f0; color: #000; border: none; padding: 10px 20px; cursor: pointer; font-weight: bold; }
        </style>
    </head>
    <body>
        <h1>🔒 SYSTEM ODBLOKOWANY: HASŁO "potwór" (bez cudzysłowów)</h1>
        <form method="POST">
            <input type="password" name="gate_pass" placeholder="Wprowadź klucz dostępu..." autofocus>
            <button>ODBLOKUJ</button>
        </form>
        <a href="test.htm">Wykonaj test, aby pokazać hasło. Test nie potrwa zbyt długo.</a>
        <a href="regu.htm">Akceptuję regulamin.</a>
        <?php if(isset($error)) echo "<p style='color:red'>" . htmlspecialchars($error) . "</p>"; ?>
    </body>
    </html>
    <?php
    exit();
}

$userSpecs = ['ram' => 4, 'cpu' => 2.4]; 
if (isset($_SESSION['user'])) {
    $dbFile = 'users.json';
    if(file_exists($dbFile)) {
        $db = json_decode(file_get_contents($dbFile), true);
        if (isset($db[$_SESSION['user']]['specs'])) {
            $userSpecs = $db[$_SESSION['user']]['specs'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>PingOS 26.04</title>
    <style>
        :root {
            --accent-color: #0078d4; /* Domyślny kolor */
        }

* { 
            box-sizing: border-box; 
            user-select: none; 
            /* Dodajemy kursor dla wszystkich elementów */
            cursor: url('arrow.cur'), default; 
        }
        
        body { 
            margin: 0; 
            overflow: hidden; 
            font-family: 'Segoe UI', sans-serif; 
            background: url('http://maksoft.kolejopedia.pl/webos/tapety2604/pingwin2.png') no-repeat center fixed; 
            background-size: cover; 
            transition: opacity 0.5s; 
            /* Powtarzamy kursor dla pewności w body */
            cursor: url('arrow.cur'), default;
        }


            <!-- background: url('https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80') no-repeat center fixed; background-size: cover; transition: opacity 0.5s; }
-->

            <!-- background: url('https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80') no-repeat center fixed; -->

            background-size: cover; 
            transition: opacity 0.5s;
        }

        /* --- TRYB CIEMNY (BETA) --- */
        body.dark-mode { color: #fff; }
        body.dark-mode #taskbar { background: rgba(20, 20, 20, 0.85) !important; border-top-color: #444; }
        body.dark-mode #start-menu { background: rgba(30, 30, 30, 0.9); color: #fff; border-color: #444; }
        body.dark-mode .start-app-item:hover { background: rgba(255,255,255,0.1); }
body.dark-mode .window { 
    background: rgba(30, 30, 30, 0.7); 
    border: 1px solid rgba(0, 120, 212, 0.3); /* Kolor akcentu na ramce */
    box-shadow: 0 10px 40px rgba(0,0,0,0.6), 0 0 15px rgba(0, 120, 212, 0.1); 
}

body.dark-mode .window-header {
    background: linear-gradient(to bottom, rgba(50,50,50,0.8), rgba(30,30,30,0.9));
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
        body.dark-mode .window-header span { color: #fff !important; }
        body.dark-mode .window-content { background: #1e1e1e; color: #eee; }
        body.dark-mode .taskbar-info { color: #aaa; }

        #login-screen { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(20px); z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; color: white; }
        .login-box input { display: block; margin: 10px 0; padding: 10px; width: 250px; border-radius: 4px; border:none; color: #333; }
        .login-box button { width: 100%; padding: 10px; cursor: pointer; background: var(--accent-color); color: white; border:none; border-radius: 4px; }
        
#desktop { 
    display: <?php echo isset($_SESSION['user']) ? 'grid' : 'none'; ?>; 
    padding: 20px; 
    grid-template-columns: repeat(auto-fill, 90px); 
    grid-auto-rows: 100px; 
    gap: 15px; 
    height: calc(100vh - 48px);
    align-content: start;
    /* --- NOWE LINIE --- */
    background: rgba(0, 0, 0, 0); /* Przezroczyste na starcie */
    transition: background-color 0.4s ease; /* Płynne przejście */
}

.desktop-icon { 
    position: relative; /* Potrzebne dla warstwy ::before */
    text-align: center; 
    color: white; 
    cursor: inherit; 
    padding: 10px; 
    border-radius: 8px; 
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); /* Płynniejsza animacja */
    animation: iconEntrance 0.8s ease-out backwards; /* Animacja wejścia */
    overflow: hidden; /* Zatrzymuje poświatę wewnątrz zaokrąglonych rogów */
    z-index: 1;
    user-select: none;
}

/* Warstwa efektu Glow (Vista/Win7 style) */
.desktop-icon::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    /* Wykorzystujemy zmienne JS: --mouse-x, --mouse-y oraz --hover-color */
    background: radial-gradient(
        circle 65px at var(--mouse-x, 50%) var(--mouse-y, 50%), 
        var(--hover-color, rgba(255, 255, 255, 0.3)), 
        transparent 80%
    );
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: -1; /* Poświata pod ikoną i tekstem */
    pointer-events: none; /* Nie blokuje kliknięć w ikonę */
}

@keyframes iconEntrance {
    from { opacity: 0; transform: translateY(20px) scale(0.8); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

/* Dodaj opóźnienie dla każdej kolejnej ikony (opcjonalnie w JS lub ręcznie dla kilku) */
.desktop-icon:nth-child(1) { animation-delay: 0.1s; }
.desktop-icon:nth-child(2) { animation-delay: 0.15s; }
.desktop-icon:nth-child(3) { animation-delay: 0.2s; }

     <!--   .desktop-icon:hover { background: rgba(255,255,255,0.15); backdrop-filter: blur(5px); }
-->

.desktop-icon:hover {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(5px);
    box-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
    outline: 1px solid rgba(255, 255, 255, 0.3);
}

.desktop-icon div:first-child { 
    font-size: 42px; 
    margin-bottom: 5px; 
    /* Mocny efekt głębi dla emoji/ikon */
    filter: drop-shadow(0 4px 6px rgba(0,0,0,0.4)); 
    transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.desktop-icon:hover div:first-child {
    transform: scale(1.1) translateY(-5px);
}
        .desktop-icon div:last-child { font-size: 11px; text-shadow: 1px 1px 2px #000; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }


/* Styl dla obrazka/emoji wewnątrz ikony */
.desktop-icon img, .desktop-icon .icon-placeholder {
    width: 48px;
    height: 48px;
    margin-bottom: 8px;
    display: block;
    margin-left: auto;
    margin-right: auto;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

/* Styl dla tekstu pod ikoną */
.desktop-icon span {
    font-size: 13px;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.8);
    display: block;
    word-wrap: break-word;
}

#start-menu {
    position: absolute; 
    bottom: 60px; 
    left: 10px;   
    width: 420px; 
    height: 500px; 
    background: rgba(243, 243, 243, 0.55);
    backdrop-filter: blur(20px); 
    border-radius: 12px; 
    border: 1px solid rgba(255,255,255,0.3);
    box-shadow: 0 10px 40px rgba(0,0,0,0.3); 
    display: none; 
    flex-direction: column; 
    z-index: 10001;
    padding: 20px; 
    overflow-y: auto; 
    overflow-x: hidden;

    /* Punkt zakotwiczenia animacji - lewy dolny róg */
    transform-origin: bottom left; 
}

/* Klasa wyzwalająca animację otwierania */
#start-menu.opening {
    display: flex;
    animation: startMenuPopIn 1.00s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

/* Klasa wyzwalająca animację zamykania */
#start-menu.closing {
    animation: startMenuPopOut 3.00s ease-in forwards;
}

/* Definicje klatek kluczowych */
@keyframes startMenuPopIn {
    from { 
        opacity: 0; 
        transform: scale(0.8) translateY(30px); 
    }
    to { 
        opacity: 1; 
        transform: scale(1) translateY(0); 
    }
}

@keyframes startMenuPopOut {
    from { 
        opacity: 1; 
        transform: scale(1) translateY(0); 
    }
    to { 
        opacity: 0; 
        transform: scale(0.9) translateY(20px); 
    }
}


/* Stylizacja dla Chrome, Edge i Safari */
#start-menu::-webkit-scrollbar {
    width: 8px; /* Odpowiednia szerokość */
}

#start-menu::-webkit-scrollbar-track {
    background: transparent; /* Brak tła toru */
}

#start-menu::-webkit-scrollbar-thumb {
    background-color: rgba(0, 0, 0, 0.2); /* Kolor uchwytu */
    border-radius: 20px; /* Pełne zaokrąglenie */
    border: 2px solid transparent; /* Trik: tworzy odstęp od krawędzi */
    background-clip: padding-box; /* Trik: sprawia, że border działa jak margines dla suwaka */
}

#start-menu::-webkit-scrollbar-thumb:hover {
    background-color: rgba(0, 0, 0, 0.4); /* Ciemniejszy przy najechaniu */
}

    
    /* Zmieniamy punkt początkowy animacji na lewy dół */
    transform-origin: bottom left;
    animation: slideUp 0.3s cubic-bezier(0.18, 0.89, 0.32, 1.28);
}

/* Poprawka animacji, żeby menu nie "leciało" ze środka */
@keyframes slideUp { 
    from { transform: translateY(20px); opacity: 0; } 
    to { transform: translateY(0); opacity: 1; } 
}
        .start-apps-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; overflow-y: auto; }
        .start-app-item { text-align: center; padding: 10px; border-radius: 8px; cursor: inherit; transition: 0.2s; }
        .start-app-item:hover { background: rgba(0,0,0,0.05); }

/* Zastąpujemy obecną klasę .window tym kodem */
.window { 
    position: absolute; 
    /* Bazowe tło z efektem Glassmorphism */
    background: rgba(255, 255, 255, 0.4); 
    backdrop-filter: blur(25px) saturate(180%); 
    -webkit-backdrop-filter: blur(25px) saturate(180%);
    
    border-radius: 12px; 
    
    /* Wielowarstwowy cień dla efektu głębi 3D i "miękkości" */
    box-shadow: 
        0 0 0 1px rgba(255, 255, 255, 0.5) inset, /* Mocniejszy blik wewnętrzny */
        0 10px 40px rgba(0,0,0,0.3), 
        0 0 100px rgba(0,0,0,0.1),
        0 0 15px rgba(255, 255, 255, 0.1); /* Zewnętrzna delikatna poświata */

    display: none; 
    flex-direction: column; 
    overflow: hidden; 
    border: 1px solid rgba(0, 0, 0, 0.2); 
    
    /* Animacje: wejście (popIn) oraz delikatne pulsowanie tła */
    animation: 
        popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275),
        windowFloat 6s ease-in-out infinite;
    
    transition: transform 0.2s cubic-bezier(0.2, 0, 0.2, 1), 
                box-shadow 0.3s ease, 
                background 0.3s ease;
    
    /* Zapobieganie renderowaniu artefaktów przy blurze */
    will-change: transform, backdrop-filter;
}

/* Efekt najechania - okno staje się bardziej "żywe" */
.window:hover {
    background: rgba(255, 255, 255, 0.5);
    box-shadow: 
        0 0 0 1.5px rgba(255, 255, 255, 0.6) inset, 
        0 20px 50px rgba(0,0,0,0.4), 
        0 0 20px var(--accent-color, rgba(0, 120, 215, 0.2)); /* Poświata w kolorze akcentu */
}

    .window.animating { transition: all 0.3s ease-in-out; }


/* Szklany odblask przesuwający się po oknie */
.window::after {
    content: "";
    position: absolute;
    top: -150%;
    left: -150%;
    width: 300%;
    height: 300%;
    background: linear-gradient(
        45deg,
        transparent 45%,
        rgba(255, 255, 255, 0.1) 50%,
        transparent 55%
    );
    transform: rotate(45deg);
    pointer-events: none;
    transition: all 0.6s ease;
    opacity: 0;
}

.window:hover::after {
    top: -100%;
    left: -100%;
    opacity: 0;
}

/* Subtelna animacja unoszenia się 
<!-- @keyframes windowFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}
-->



/* Standardowe wejście okna */
@keyframes popIn {
    from { 
        opacity: 0; 
        transform: scale(0.9) translateY(20px); 
    }
    to { 
        opacity: 1; 
        transform: scale(1) translateY(0); 
    }
}


/* Skeumorficzny nagłówek okna */
.window-header { 
    background: linear-gradient(to bottom, 
        rgba(255,255,255,0.005) 0%, 
        rgba(240,240,240,0.01) 100%); 
    padding: 12px; 
    cursor: arrow.cur; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    border-bottom: 1px solid rgba(0,0,0,0.1);
    text-shadow: 0 1px 1px rgba(255,255,255,0.8); /* Lekki połysk pod tekstem */
}
        .window-content { flex-grow: 1; position: relative; overflow: auto; background: white; }
        
        .win-controls { display: flex; gap: 8px; }
.win-controls span { 
    display: inline-block; 
    width: 16px; 
    height: 16px; 
    border-radius: 50%; 
    cursor: inherit; 
    border: 1px solid rgba(0,0,0,0.2);
    /* Gradient nadający efekt sferyczny */
    background: radial-gradient(circle at 5px 5px, rgba(255,255,255,0.8), rgba(0,0,0,0.1));
    box-shadow: inset 0 -2px 4px rgba(0,0,0,0.2);
    transition: filter 0.2s;
}

.win-controls span:hover {
    filter: brightness(1.2);
}

        .window.maximized {
            top: 0 !important; left: 0 !important;
            width: 100% !important; height: calc(100vh - 48px) !important;
            border-radius: 0; transform: none;
        }

.context-menu {
    position: absolute;
    background: #fff;
    border: 1px solid #ccc;
    box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
    display: none;
    z-index: 10005;
    min-width: 150px;
    border-radius: 4px;
    padding: 5px 0;
}
.context-menu-item {
    padding: 8px 15px;
    cursor: pointer;
    font-size: 13px;
    color: #333;
}
.context-menu-item:hover {
    background: var(--accent-color);
    color: white;
}



        
#taskbar { 
    position: absolute; 
    bottom: 0; 
    width: 100%; 
    height: 48px; 
    background: linear-gradient(to bottom, 
        rgba(255, 255, 255, 0.3) 0%, 
        rgba(200, 200, 200, 0.2) 50%, 
        rgba(100, 100, 100, 0.3) 100%); 
    backdrop-filter: blur(20px) brightness(1.1); 
    display: flex; 
    
    justify-content: flex-start; /* Zmieniono z center */
    align-items: center; 
    padding-left: 10px; /* Oddech dla ikon od lewej strony */
    
    border-top: 1px solid rgba(255,255,255,0.5); 
    z-index: 10000;
    box-shadow: 0 -5px 15px rgba(0,0,0,0.2);
}

        .taskbar-icon { font-size: 24px; margin: 0 8px; cursor: inherit; padding: 8px; border-radius: 4px; transition: 0.2s; }


.taskbar-app-icon {
    /* Efekt wciśniętego przycisku dla aktywnych apek */
    background: linear-gradient(145deg, rgba(255,255,255,0.2), rgba(0,0,0,0.05));
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 8px;
    margin: 0 4px;
    box-shadow: 2px 2px 5px rgba(0,0,0,0.1);
}

        /* Zastosowanie Barwy Systemu dla aktywnych elementów */
        .taskbar-icon:active { color: var(--accent-color); }
        #save-btn { background: var(--accent-color) !important; }

.taskbar-info {
    position: absolute;
    right: 15px; /* Zmieniono z left na right */
    font-size: 11px;
    color: #444;
    font-weight: 500;
    pointer-events: none;
}

<!--
         .taskbar-icon.active-start {
            color: var(--accent-color);
            background: rgba(0,0,0,0.1);
        }

-->

.taskbar-icon.active-start {
    color: var(--accent-color);
    background: rgba(255, 255, 255, 0.2);
    box-shadow: inset 0 0 5px rgba(0,0,0,0.1);
}

        #system-notification {
            position: absolute;
            right: 20px;
            color: #fff;
            background: #e74c3c;
            padding: 4px 12px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 11px;
            display: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            animation: blinkRed 1s infinite;
            cursor: inherit;
            z-index: 10002;
        }
        @keyframes blinkRed {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.05); }
            100% { opacity: 1; transform: scale(1); }
        }

        #bsod {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: #0078d4; color: white; z-index: 999999;
            display: none; flex-direction: column; justify-content: center; padding: 10%;
        }

        /* --- SYSTEM DIALOGS --- */
/* --- SKEUMORFICZNE SYSTEM DIALOGS (STYL RETRO-3D / GLOSSY) --- */
.sys-dialog-overlay {
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 100%;
    background: rgba(0, 0, 0, 0.45); 
    backdrop-filter: blur(8px);
    display: flex; 
    align-items: center; 
    justify-content: center; 
    z-index: 20000;
}

.sys-dialog {
    /* Trójwymiarowa, gruba, plastikowa/metalowa obudowa */
    background: linear-gradient(to bottom, #eeeeee 0%, #cccccc 100%); 
    border-radius: 8px; 
    width: 380px; 
    
    /* Wypukła ramka 3D (jasna góra i lewa krawędź, ciemny dół i prawa krawędź) */
    border-top: 2px solid #ffffff;
    border-left: 2px solid #ffffff;
    border-right: 2px solid #666666;
    border-bottom: 2px solid #444444;
    
    /* Głęboki, realistyczny cień rzucany na pulpit */
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6), inset 1px 1px 0px #fff; 
    overflow: hidden;
    animation: dialogPop 0.25s cubic-bezier(0.18, 0.89, 0.32, 1.28);
}

@keyframes dialogPop { 
    from { transform: scale(0.85); opacity: 0; } 
    to { transform: scale(1); opacity: 1; } 
}

.sys-dialog-header { 
    /* Błyszczący pasek tytułowy z efektem żelowej/szklanej kapsuły (Glossy effect) */
    padding: 12px 15px; 
    font-weight: bold; 
    color: #ffffff;
    font-family: 'Segoe UI', sans-serif;
    text-shadow: 0px 1px 2px rgba(0, 0, 0, 0.8);
    background: linear-gradient(to bottom, 
        #5b9bd5 0%, 
        #2c6ea7 50%, 
        #1d4e78 51%, 
        #3a82c4 100%);
    border-bottom: 1px solid #143552;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4);
}

.sys-dialog-body { 
    /* Wgnieciony, jasny kontener na treść komunikatu */
    padding: 22px; 
    font-size: 14px; 
    color: #000000; 
    background: #ffffff;
    margin: 6px;
    border-top: 2px solid #888888;
    border-left: 2px solid #888888;
    border-right: 1px solid #e0e0e0;
    border-bottom: 1px solid #e0e0e0;
    box-shadow: inset 1px 1px 3px rgba(0,0,0,0.2);
}

.sys-dialog-footer { 
    padding: 12px; 
    display: flex; 
    justify-content: flex-end; 
    gap: 12px; 
    background: transparent; 
}

/* --- SUPER SKEUMORFICZNE PRZYCISKI --- */
.sys-btn { 
    padding: 6px 20px; 
    border-radius: 4px; 
    font-weight: bold; 
    font-size: 13px;
    transition: all 0.1s ease; 
    cursor: url('arrow.cur'), default;
    text-shadow: 0 1px 0 rgba(255, 255, 255, 0.6);
}

/* Przycisk Główny (np. OK / Zatwierdź) - Wypukły, niebieski żelowy */
.sys-btn-primary { 
    color: #ffffff;
    text-shadow: 0 -1px 1px rgba(0,0,0,0.5);
    background: linear-gradient(to bottom, #7abcff 0%, #60abf8 44%, #4096ee 45%, #1175e3 100%);
    border-top: 1px solid #ffffff;
    border-left: 1px solid #ffffff;
    border-right: 2px solid #0e4e94;
    border-bottom: 2px solid #0b3d75;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.4);
}

.sys-btn-primary:hover {
    filter: brightness(1.1);
}

.sys-btn-primary:active {
    /* Efekt fizycznego wciśnięcia przycisku w głąb obudowy */
    background: linear-gradient(to bottom, #1175e3 0%, #4096ee 100%);
    border-top: 2px solid #0b3d75;
    border-left: 2px solid #0b3d75;
    border-right: 1px solid #ffffff;
    border-bottom: 1px solid #ffffff;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.4);
    transform: translate(1px, 1px);
}

/* Przycisk Dodatkowy (np. Anuluj) - Klasyczny, plastikowy klikalny przycisk */
.sys-btn-secondary { 
    color: #333333; 
    background: linear-gradient(to bottom, #ffffff 0%, #eaeaea 100%);
    border-top: 1px solid #ffffff;
    border-left: 1px solid #ffffff;
    border-right: 2px solid #999999;
    border-bottom: 2px solid #777777;
    box-shadow: 0 2px 3px rgba(0,0,0,0.15);
}

.sys-btn-secondary:hover {
    background: linear-gradient(to bottom, #ffffff 0%, #f5f5f5 100%);
}

.sys-btn-secondary:active {
    background: linear-gradient(to bottom, #dcdcdc 0%, #f2f2f2 100%);
    border-top: 2px solid #666666;
    border-left: 2px solid #666666;
    border-right: 1px solid #ffffff;
    border-bottom: 1px solid #ffffff;
    box-shadow: inset 0 2px 3px rgba(0,0,0,0.2);
    transform: translate(1px, 1px);
}

/* Wgniecione pole tekstowe dla zapytań systemowych (Prompt) */
.sys-dialog-input { 
    width: 100%; 
    padding: 8px; 
    margin-top: 12px; 
    background: #ffffff;
    color: #000000;
    border-top: 2px solid #777777;
    border-left: 2px solid #777777;
    border-right: 1px solid #dddddd;
    border-bottom: 1px solid #dddddd;
    border-radius: 2px;
    outline: none; 
    box-shadow: inset 1px 1px 3px rgba(0, 0, 0, 0.3);
}
	.taskbar-app-icon {
	    width: 36px;
	    height: 36px;
	    display: flex;
	    align-items: center;
	    justify-content: center;
	    font-size: 20px;
	    cursor: inherit;
	    border-radius: 4px;
	    transition: 0.2s;
	    background: rgba(0,0,0,0.05);
}

.taskbar-app-icon:hover { background: rgba(0,0,0,0.1); }
.taskbar-app-icon.active {
    border-bottom: 3px solid var(--accent-color);
    background: rgba(255, 255, 255, 0.2);
    /* Zmieniamy na 8 sekund, żeby mrygało rzadziej */
    animation: pulseActiveSlow 8s infinite; 
}

@keyframes pulseActiveSlow {
    0% { box-shadow: 0 0 0 0px rgba(0, 120, 212, 0.4); }
    15% { box-shadow: 0 0 0 10px rgba(0, 120, 212, 0); } /* Puls kończy się szybko */
    20% { box-shadow: 0 0 0 0px rgba(0, 120, 212, 0); }  /* Powrót do stanu zero */
    100% { box-shadow: 0 0 0 0px rgba(0, 120, 212, 0); } /* Reszta czasu to cisza */
}

	/* Uchwyt zmiany rozmiaru */
	.resizer {
  	  width: 15px;
 	   height: 15px;
 	   background: transparent;
 	   position: absolute;
 	   right: 0;
 	   bottom: 0;
 	   cursor: nwse-resize; /* Kursor skośny */
 	   z-index: 10;
	}

	/* Opcjonalnie: wizualny znacznik w rogu (kropki) */
		.resizer::after {
   	 content: "";
  	 position: absolute;
  	 right: 3px;
  	 bottom: 3px;
         width: 5px;
         height: 5px;
         border-right: 2px solid rgba(0,0,0,0.3);
         border-bottom: 2px solid rgba(0,0,0,0.3);
}

body.dark-mode .resizer::after {
    border-right-color: rgba(255,255,255,0.3);
    border-bottom-color: rgba(255,255,255,0.3);
}

        #shutdown-screen {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: #000; color: #ff8c00; z-index: 1000000;
            display: none; flex-direction: column; align-items: center; justify-content: center;
            font-family: 'Courier New', monospace; text-align: center; font-weight: bold;
        }

        .system-frozen { pointer-events: none !important; cursor: wait !important; }

/* --- WYGASZACZ EKRANU --- */
#screensaver {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: #000;
    z-index: 9999999; /* Musi być na samej górze */
    display: none; /* Domyślnie ukryty */
    cursor: none; /* Ukrywamy kursor myszy */
}

#screensaver-content {
    position: absolute;
    font-size: 140px;
    font-weight: bold;
    color: var(--accent-color);
    text-shadow: 0 0 10px var(--accent-color);
    /* Tutaj zostawiamy TYLKO latanie */
    animation: 
        bounceX 2.17s linear infinite alternate, 
        bounceY 3.61s linear infinite alternate;
}

#pingwin-spinner {
    display: inline-block; /* Ważne, żeby rotacja działała */
    /* Tutaj dajemy TYLKO kręcenie */
    animation: spin 3s linear infinite;
}

/* Definicja pełnego obrotu o 360 stopni */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes bounceX {
    from { left: 0; }
    to { left: calc(100vw - 200px); } /* Odejmujemy szerokość napisu */
}

@keyframes bounceY {
    from { top: 0; }
    to { top: calc(100vh - 100px); } /* Odejmujemy wysokość napisu */
}

/* --- NAKŁADKA FULLSCREEN --- */
#fullscreen-overlay {
    display: none; 
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9); /* Ciemne tło */
    backdrop-filter: blur(10px); /* Efekt rozmycia jak w reszcie systemu */
    color: white;
    z-index: 99999; /* Musi być wyżej niż taskbar i okna */
    flex-direction: column;
    justify-content: center;
    align-items: center;
    font-family: 'Segoe UI', sans-serif;
    text-align: center;
}

#fullscreen-overlay h1 {
    font-size: 3rem;
    color: var(--accent-color);
    margin-bottom: 10px;
}

#fullscreen-overlay p {
    font-size: 1.2rem;
    opacity: 0.8;
}

/* Kontener na legendarne paski ZX Spectrum w oknie */
.zx-spectrum-stripes {
    display: flex;
    width: 32px;
    height: 12px;
    margin-right: 10px;
    border-radius: 2px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0,0,0,0.2);
    flex-shrink: 0;
}

.zx-spectrum-stripes div {
    flex: 1;
    height: 100%;
}

/* Kolory: Czerwony, Żółty, Niebieski, Zielony */
.zx-stripe-red    { background: #D03D28; }
.zx-stripe-yellow { background: #F7D51D; }
.zx-stripe-blue   { background: #40B4E5; }
.zx-stripe-green  { background: #A0C645; }

#gamepad-cursor {
    width: 15px;
    height: 15px;
    background-color: #ff0000;
    border: 2px solid #ffffff;
    border-radius: 50%;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1000000; /* Musi być nad wszystkim */
    pointer-events: none; /* Żeby kursor nie zasłaniał kliknięć */
    display: none;
    box-shadow: 0 0 10px rgba(0,0,0,0.5);
}

/* --- ZMNIEJSZONE PRZEZROCZYSTOŚCI (WERSJA WYPUKŁA (nie wiem gdzie idioto) - JASNA) --- */
/* --- ZMNIEJSZONE PRZEZROCZYSTOŚCI (WERSJA WYPUKŁA - JASNA) --- */
/* --- ZMNIEJSZONE PRZEZROCZYSTOŚCI (WERSJA WYPUKŁA - JASNA) --- */
body.reduce-transparency:not(.dark-mode) #taskbar {
    /* Stopniowe, płynne ściemnianie od jasnej góry aż do zdecydowanie ciemniejszego dołu */
    background: linear-gradient(to bottom, #f9f9f9 0%, #e1e1e1 45%, #bebebe 100%) !important;
    
    /* Gruba, biała krawędź u góry odbijająca światło – jedyne ostre odcięcie */
    border-top: 2px solid #ffffff !important;
    
    /* 1. inset 0 1px 0 #fff - dodatkowe rozświetlenie tuż pod ramką
       2. 0 -5px 15px rgba(0,0,0,0.25) - cień rzucany na pulpit powyżej paska */
    box-shadow: 
        inset 0 1px 0 #ffffff, 
        0 -5px 15px rgba(0,0,0,0.25) !important;
    
    backdrop-filter: none !important;
}

body.reduce-transparency:not(.dark-mode) .window {
    background: #eaeaea !important; /* Bardziej zwarty, nieprzezroczysty plastik */
    
    /* Klasyczna, twarda ramka 3D wokół okna */
    border-top: 2px solid #ffffff !important;
    border-left: 2px solid #ffffff !important;
    border-right: 2px solid #808080 !important;
    border-bottom: 2px solid #666666 !important;
    
    box-shadow: 
        inset 1px 1px 0px #ffffff, 
        inset -1px -1px 0px #aeaeae, 
        0 15px 35px rgba(0,0,0,0.4) !important;
    backdrop-filter: none !important;
}

body.reduce-transparency:not(.dark-mode) .window-header {
    /* Kapsułowy nagłówek żelowy, pasujący do Twoich przycisków .sys-dialog-header */
    background: linear-gradient(to bottom, #ffffff 0%, #eaeaea 49%, #dcdcdc 50%, #f2f2f2 100%) !important;
    border-bottom: 2px solid #999999 !important;
}

body.reduce-transparency:not(.dark-mode) .window-content {
    background: #ffffff !important;
    border-top: 2px solid #777777 !important;
    border-left: 2px solid #777777 !important;
    border-right: 1px solid #dddddd !important;
    border-bottom: 1px solid #dddddd !important;
    margin: 4px;
}

body.reduce-transparency:not(.dark-mode) #start-menu {
    background: #e8e8e8 !important;
    border-top: 2px solid #ffffff !important;
    border-left: 2px solid #ffffff !important;
    border-right: 2px solid #777777 !important;
    border-bottom: 2px solid #666666 !important;
    box-shadow: inset 1px 1px 0px #fff, 5px 5px 25px rgba(0,0,0,0.3) !important;
    backdrop-filter: none !important;
}

/* Efekt rozświetlenia Vista/Win7 dla paska, menu i nagłówków */
/* Poprawiony efekt Glow - bezpieczniejszy dla układu */
/* Metoda bezpośredniego tła - bezpieczna dla układu */
/* Przezroczyste tło Aero z efektem Glow */
/* 1. Wspólne właściwości: Blask (Glow) i Rozmycie (Blur) */
body:not(.dark-mode):not(.reduce-transparency) #taskbar,
body:not(.dark-mode):not(.reduce-transparency) #start-menu,
body:not(.dark-mode):not(.reduce-transparency) .window-header {
    background-repeat: no-repeat;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    /* Dynamiczny gradient blasku */
    background-image: radial-gradient(
        circle 150px at var(--glow-x, -1000px) var(--glow-y, -1000px), 
        rgba(255, 255, 255, 0.4), 
        transparent 100%
    );
}

/* 2. Pasek zadań i Menu Start - lekka mgiełka (0.15) */
body:not(.dark-mode):not(.reduce-transparency) #taskbar,
body:not(.dark-mode):not(.reduce-transparency) #start-menu {
    background-color: rgba(255, 255, 255, 0.15);
}

/* 3. Window Header - maksymalna przezroczystość (0.02) */
body:not(.dark-mode):not(.reduce-transparency) .window-header {
    background-color: rgba(255, 255, 255, 0.02);
    /* Dodajemy bardzo subtelną linię u dołu, żeby oddzielić nagłówek od treści okna */
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}


/* =================================================================
   CENTRUM POWIADOMIEŃ — NOTIFICATION CENTER
   ================================================================= */

/* ---- Prawy kontener paska zadań ---- */
#taskbar-right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 6px;
    padding-right: 12px;
    flex-shrink: 0;
}
#taskbar-right .taskbar-info {
    position: static;
    right: auto;
    white-space: nowrap;
}

/* ---- Dzwonek ---- */
#notif-bell {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    font-size: 19px;
    border-radius: 8px;
    cursor: inherit;
    transition: background 0.2s;
    background: rgba(0,0,0,0.05);
    flex-shrink: 0;
    user-select: none;
}
#notif-bell:hover { background: rgba(0,0,0,0.12); }
body.dark-mode #notif-bell:hover { background: rgba(255,255,255,0.12); }

/* Badge licznika */
#notif-badge {
    position: absolute;
    top: -3px; right: -4px;
    background: #e74c3c;
    color: #fff;
    font-size: 9px;
    font-weight: 800;
    min-width: 16px;
    height: 16px;
    border-radius: 8px;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 0 3px;
    pointer-events: none;
    border: 2px solid rgba(255,255,255,0.9);
    animation: badgePop 0.3s cubic-bezier(0.175,0.885,0.32,1.275);
    line-height: 1;
    letter-spacing: 0;
}
@keyframes badgePop {
    from { transform: scale(0); }
    to   { transform: scale(1); }
}
#notif-bell.has-notif #notif-badge { display: flex; }

/* ---- Nakładka do zamknięcia panelu kliknięciem poza nim ---- */
#notif-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 10002;
}
#notif-overlay.active { display: block; }

/* ---- Panel centrum powiadomień ---- */
#notif-panel {
    position: fixed;
    top: 0; right: -420px;
    width: 400px;
    height: 100vh;
    background: rgba(238, 240, 245, 0.72);
    backdrop-filter: blur(32px) saturate(190%);
    -webkit-backdrop-filter: blur(32px) saturate(190%);
    border-left: 1px solid rgba(255,255,255,0.55);
    box-shadow: -12px 0 50px rgba(0,0,0,0.22);
    z-index: 10003;
    display: flex;
    flex-direction: column;
    transition: right 0.35s cubic-bezier(0.4,0,0.2,1);
    font-family: 'Segoe UI', sans-serif;
}
#notif-panel.open { right: 0; }
body.dark-mode #notif-panel {
    background: rgba(20,22,35,0.88);
    border-left-color: rgba(255,255,255,0.08);
}

/* Nagłówek */
#notif-panel-header {
    padding: 18px 16px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(0,0,0,0.08);
    flex-shrink: 0;
    gap: 8px;
}
body.dark-mode #notif-panel-header { border-bottom-color: rgba(255,255,255,0.07); }
#notif-panel-header h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #1a1a2e;
    display: flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
}
body.dark-mode #notif-panel-header h2 { color: #ececec; }
.notif-header-actions { display: flex; gap: 5px; align-items: center; }
.notif-header-btn {
    background: rgba(0,0,0,0.06);
    border: none;
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 11px;
    font-weight: 600;
    cursor: inherit;
    color: #444;
    transition: 0.18s;
    white-space: nowrap;
}
.notif-header-btn:hover { background: rgba(0,0,0,0.13); color: #111; }
body.dark-mode .notif-header-btn { background: rgba(255,255,255,0.08); color: #bbb; }
body.dark-mode .notif-header-btn:hover { background: rgba(255,255,255,0.16); color: #fff; }
.notif-close-btn {
    background: rgba(231,76,60,0.1) !important;
    color: #c0392b !important;
    width: 28px; height: 28px;
    padding: 0 !important;
    display: flex !important;
    align-items: center;
    justify-content: center;
    font-size: 15px !important;
    border-radius: 50% !important;
}
.notif-close-btn:hover { background: #e74c3c !important; color: #fff !important; }

/* Filtry */
#notif-filter-bar {
    display: flex;
    gap: 5px;
    padding: 8px 14px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    flex-shrink: 0;
    flex-wrap: wrap;
}
body.dark-mode #notif-filter-bar { border-bottom-color: rgba(255,255,255,0.06); }
.notif-filter-btn {
    padding: 3px 11px;
    border: none;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    cursor: inherit;
    background: rgba(0,0,0,0.06);
    color: #555;
    transition: 0.18s;
}
.notif-filter-btn.active { background: var(--accent-color); color: #fff; }
.notif-filter-btn:hover:not(.active) { background: rgba(0,0,0,0.12); }
body.dark-mode .notif-filter-btn { background: rgba(255,255,255,0.07); color: #aaa; }
body.dark-mode .notif-filter-btn.active { background: var(--accent-color); color: #fff; }

/* Lista */
#notif-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px 10px;
    display: flex;
    flex-direction: column;
    gap: 7px;
}
#notif-list::-webkit-scrollbar { width: 4px; }
#notif-list::-webkit-scrollbar-track { background: transparent; }
#notif-list::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.14); border-radius: 10px; }

/* Etykieta grupy */
.notif-group-label {
    font-size: 10px;
    font-weight: 700;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 6px 4px 2px;
}
body.dark-mode .notif-group-label { color: #555; }

/* Pojedyncze powiadomienie */
.notif-item {
    background: rgba(255,255,255,0.72);
    border-radius: 10px;
    padding: 11px 13px;
    border: 1px solid rgba(0,0,0,0.06);
    border-left: 4px solid #ccc;
    display: flex;
    flex-direction: column;
    gap: 5px;
    position: relative;
    transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.25s ease;
    animation: notifItemIn 0.3s cubic-bezier(0.175,0.885,0.32,1.275) backwards;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    cursor: inherit;
}
body.dark-mode .notif-item {
    background: rgba(255,255,255,0.06);
    border-color: rgba(255,255,255,0.07);
}
.notif-item:hover { transform: translateX(-3px); box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
.notif-item.unread { background: rgba(255,255,255,0.9); }
body.dark-mode .notif-item.unread { background: rgba(255,255,255,0.11); }

/* Kolory lewego bordera wg typu */
.notif-item[data-type="chat"]    { border-left-color: #3498db; }
.notif-item[data-type="system"]  { border-left-color: #27ae60; }
.notif-item[data-type="warning"] { border-left-color: #e67e22; }
.notif-item[data-type="info"]    { border-left-color: #8e44ad; }
.notif-item[data-type="error"]   { border-left-color: #e74c3c; }

@keyframes notifItemIn {
    from { opacity: 0; transform: translateX(18px); }
    to   { opacity: 1; transform: translateX(0); }
}

/* Górna linia: ikona + tytuł + czas + X */
.notif-item-top {
    display: flex;
    align-items: center;
    gap: 7px;
}
.notif-icon { font-size: 17px; flex-shrink: 0; line-height: 1; }
.notif-title {
    font-size: 12px;
    font-weight: 700;
    color: #1a1a2e;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
body.dark-mode .notif-title { color: #eee; }
.notif-time { font-size: 10px; color: #bbb; flex-shrink: 0; }
.notif-dismiss {
    background: none; border: none;
    color: #ccc; font-size: 13px;
    cursor: inherit; padding: 0;
    width: 20px; height: 20px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%; transition: 0.15s; flex-shrink: 0;
}
.notif-dismiss:hover { background: rgba(231,76,60,0.15); color: #e74c3c; }
.notif-message {
    font-size: 12px;
    color: #666;
    line-height: 1.45;
    margin-left: 24px;
    word-break: break-word;
}
body.dark-mode .notif-message { color: #999; }
.notif-action-btn {
    margin-left: 24px;
    margin-top: 3px;
    background: var(--accent-color);
    color: #fff;
    border: none;
    border-radius: 5px;
    padding: 3px 11px;
    font-size: 11px;
    font-weight: 600;
    cursor: inherit;
    transition: 0.18s;
    align-self: flex-start;
}
.notif-action-btn:hover { filter: brightness(1.15); }

/* Stan pusty */
#notif-empty-state {
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
    gap: 8px;
    color: #aaa;
    text-align: center;
    padding: 40px 20px;
}
#notif-empty-state .empty-icon { font-size: 52px; opacity: 0.35; filter: grayscale(1); }
#notif-empty-state p { font-size: 14px; font-weight: 600; margin: 0; }
#notif-empty-state small { font-size: 12px; opacity: 0.65; }

/* Stopka panelu */
#notif-panel-footer {
    padding: 8px 16px;
    border-top: 1px solid rgba(0,0,0,0.06);
    font-size: 10px;
    color: #bbb;
    text-align: center;
    flex-shrink: 0;
}
body.dark-mode #notif-panel-footer { border-top-color: rgba(255,255,255,0.06); }

/* ---- TOAST POP-UPY ---- */
#toast-container {
    position: fixed;
    bottom: 58px;  /* Nad taskbarem */
    right: 14px;
    z-index: 10006;
    display: flex;
    flex-direction: column-reverse;
    gap: 8px;
    pointer-events: none;
    max-width: 340px;
    width: 340px;
}
.toast-notif {
    background: rgba(252,252,254,0.93);
    backdrop-filter: blur(22px) saturate(180%);
    -webkit-backdrop-filter: blur(22px) saturate(180%);
    border-radius: 12px;
    padding: 13px 15px;
    border: 1px solid rgba(0,0,0,0.08);
    border-left: 4px solid var(--accent-color);
    box-shadow: 0 8px 32px rgba(0,0,0,0.16);
    pointer-events: all;
    animation: toastSlideIn 0.38s cubic-bezier(0.175,0.885,0.32,1.275);
    overflow: hidden;
    transition: opacity 0.28s ease, transform 0.28s ease;
    position: relative;
    cursor: inherit;
}
body.dark-mode .toast-notif {
    background: rgba(28,30,45,0.96);
    border-color: rgba(255,255,255,0.09);
}
.toast-notif.toast-hiding {
    opacity: 0;
    transform: translateX(24px) scale(0.96);
}
/* Kolor bordera wg typu */
.toast-notif[data-type="chat"]    { border-left-color: #3498db; }
.toast-notif[data-type="system"]  { border-left-color: #27ae60; }
.toast-notif[data-type="warning"] { border-left-color: #e67e22; }
.toast-notif[data-type="info"]    { border-left-color: #8e44ad; }
.toast-notif[data-type="error"]   { border-left-color: #e74c3c; }

.toast-top { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.toast-icon { font-size: 15px; flex-shrink: 0; }
.toast-title { font-size: 12px; font-weight: 700; color: #1a1a2e; flex: 1; }
body.dark-mode .toast-title { color: #eee; }
.toast-close {
    background: none; border: none;
    color: #bbb; font-size: 13px;
    cursor: inherit; padding: 0 2px;
    line-height: 1; transition: color 0.15s;
    flex-shrink: 0;
}
.toast-close:hover { color: #e74c3c; }
.toast-message { font-size: 12px; color: #666; line-height: 1.4; }
body.dark-mode .toast-message { color: #999; }

/* Pasek postępu */
.toast-progress {
    position: absolute;
    bottom: 0; left: 0;
    height: 3px;
    background: currentColor;
    opacity: 0.35;
    border-radius: 0 0 12px 0;
    width: 100%;
    transition: width linear;
}
.toast-notif[data-type="chat"]    .toast-progress { color: #3498db; }
.toast-notif[data-type="system"]  .toast-progress { color: #27ae60; }
.toast-notif[data-type="warning"] .toast-progress { color: #e67e22; }
.toast-notif[data-type="info"]    .toast-progress { color: #8e44ad; }
.toast-notif[data-type="error"]   .toast-progress { color: #e74c3c; }
.toast-notif:not([data-type])     .toast-progress { color: var(--accent-color); }

@keyframes toastSlideIn {
    from { opacity: 0; transform: translateX(28px) scale(0.94); }
    to   { opacity: 1; transform: translateX(0)   scale(1); }
}

    </style>
</head>
<?php if (isset($_SESSION['user'])): ?>
    <div id="init-screen" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #000; z-index: 3000000; display: flex; align-items: center; justify-content: center; color: #0f0; font-family: 'Courier New', monospace; text-align: center; flex-direction: column;">
        <div id="init-text" style="font-size: 24px; letter-spacing: 2px; animation: blink 1s infinite;">
            NACIŚNIJ ENTER, ABY ZAINICJOWAĆ SYSTEM
        </div>
    </div>

    <div id="splash-screen" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: black; z-index: 2900000; display: none; align-items: center; justify-content: center; overflow: hidden;">
        <img src="tapety2604/NGC_2683_Spiral_galaxy (NASA).jpg" style="width: 100%; height: 100%; object-fit: cover;" alt="Logo">
        <audio id="start-sound" src="intro.opus" preload="auto"></audio>
        <audio id="chat-sound"  src="chat.mp3"   preload="auto" volume="0.5"></audio>
    </div>

    <style>
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0; } 100% { opacity: 1; } }
        
        .penguin-question-item {
            padding: 6px 8px;
            margin: 4px 0;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(243, 156, 18, 0.4);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: left;
        }
        .penguin-question-item:hover {
            background: #f39c12;
            color: #fff;
            transform: translateY(-1px);
        }
        .penguin-back-btn {
            margin-top: 8px;
            padding: 4px 10px;
            background: #e67e22;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            font-weight: bold;
            transition: background 0.2s;
        }
        .penguin-back-btn:hover {
            background: #d35400;
        }
    </style>
<?php endif; ?>
<?php if (!isset($_SESSION['user'])): ?>
    <div id="login-screen">
        <div style="width: 120px; height: 120px; background: #ddd; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 80px; margin-bottom: 20px;">🐧</div>
        <h2>Witaj w PingOS</h2>
        <div class="login-box">
            <input type="text" id="u-name" placeholder="Użytkownik">
            <input type="password" id="u-pass" placeholder="Hasło">
            <button onclick="auth('login')">Zaloguj</button>
            <button onclick="auth('register')" style="background: transparent; border: 1px solid rgba(255,255,255,0.3); margin-top:10px;">Utwórz konto</button>
        </div>
    </div>
<?php else: ?>
    <div id="desktop"></div>
    <div id="wallpaper-penguin" style="position: absolute; left: 150px; top: 150px; z-index: 2; cursor: grab; width: 150px; height: auto; user-select: none;">
        <img src="pingwi3.png" style="width: 100%; height: auto; pointer-events: none;" alt="Pingwinek">
        <!-- Chmurka Clippy -->
        <div id="penguin-bubble" style="
            display: none;
            position: absolute;
            bottom: 110%;
            left: 50%;
            transform: translateX(-50%);
            width: 220px;
            background: rgba(255, 255, 240, 0.95);
            border: 2px solid #f39c12;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            padding: 12px;
            font-family: 'Segoe UI', sans-serif;
            font-size: 13px;
            color: #333;
            z-index: 100;
            cursor: default;
            backdrop-filter: blur(5px);
        " onclick="event.stopPropagation()" onmousedown="event.stopPropagation()">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f39c12; padding-bottom: 4px; margin-bottom: 8px; font-weight: bold; color: #d35400;">
                <span>Pomocnik PingChat</span>
                <span id="close-penguin-bubble" style="cursor: pointer; font-weight: bold; padding: 0 4px;" onclick="event.stopPropagation(); togglePenguinBubble()">✕</span>
            </div>
            <div id="penguin-bubble-content"></div>
            <!-- Strzałka chmurki -->
            <div style="
                position: absolute;
                top: 100%;
                left: 50%;
                transform: translateX(-50%);
                width: 0;
                height: 0;
                border-left: 10px solid transparent;
                border-right: 10px solid transparent;
                border-top: 10px solid #f39c12;
            "></div>
            <div style="
                position: absolute;
                top: 99%;
                left: 50%;
                transform: translateX(-50%);
                width: 0;
                height: 0;
                border-left: 9px solid transparent;
                border-right: 9px solid transparent;
                border-top: 9px solid rgba(255, 255, 240, 0.95);
            "></div>
        </div>
    </div>

<div id="gamepad-cursor" style="
    width: 20px; 
    height: 20px; 
    background: red; 
    border-radius: 50%; 
    position: fixed; 
    top: 0; 
    left: 0; 
    z-index: 999999; 
    pointer-events: none; 
    display: none;
    border: 2px solid white;
"></div>

    <div id="start-menu" onclick="event.stopPropagation()">
        <span style="font-size:10px;">PingOS 26.04: wersja 1.3.1 (build 2026+016)</span>
        <div style="font-size: 14px; font-weight: bold; margin-bottom: 15px;">Twoje aplikacje</div>
        <div id="start-apps-list" class="start-apps-grid"></div>
        <div style="margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div id="user-avatar" style="width:32px; height:32px; background: var(--accent-color); border-radius:50%; color:white; display:flex; align-items:center; justify-content:center; font-size:14px;">
                    <?php echo strtoupper(substr(htmlspecialchars($_SESSION['user']), 0, 1)); ?>
                </div>
                <span style="font-size:12px;"><?php echo htmlspecialchars($_SESSION['user'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <button onclick="systemRestart()" title="Restartuj PingOS" style="background:none; border:none; cursor:inherit; font-size:18px;">🔄</button>
                <button onclick="systemShutdown()" title="Wyłącz system" style="background:none; border:none; cursor:inherit; font-size:18px;">⏻</button>
                <button onclick="auth('logout')" title="Wyloguj" style="background:none; border:none; cursor:inherit; font-size:18px;">⭕</button>
                <button onclick="toggleScreensaver()" title="Włącz wygaszacz" style="background:none; border:none; cursor:inherit; font-size:18px;">🖥️</button>
                <button onclick="toggleScreensaver()" title="Włącz pełny ekran/przywróć kursor" style="background:none; border:none; cursor:inherit; font-size:18px;">🥚</button>
            </div>
        </div>
    </div>

<div id="app-context-menu" class="context-menu">
   <!-- <div class="context-menu-item" onclick="moveIcon()">Przenieś ikonę</div> -->
    <div class="context-menu-item" onclick="uninstallApp()" style="color: red;">Odinstaluj</div>
</div>

<div id="taskbar">
        <div class="taskbar-icon" onclick="toggleStartMenu(event)" title="Start">Super</div>
        <p>|</p>
        <div class="taskbar-icon" onclick="refreshDesktop()" title="Odśwież Pulpit">🔄</div>
        <div id="taskbar-apps" style="display: flex; gap: 5px; margin: 0 15px; border-left: 1px solid rgba(0,0,0,0.1); padding-left: 10px;"></div>
        <div id="taskbar-right">
            <div class="taskbar-info"><span id="system-clock">00:00:00</span></div>
            <div id="notif-bell" onclick="toggleNotifPanel(event)" title="Centrum Powiadomień (Alt+N)">
                🔔<span id="notif-badge">0</span>
            </div>
        </div>
        <div id="system-notification" style="display:none;"></div>
    </div>
<?php endif; ?>

<div id="windows-container"></div>

<!-- ===================================================
     CENTRUM POWIADOMIEŃ — PANEL + OVERLAY + TOASTY
     =================================================== -->
<div id="notif-overlay" onclick="closeNotifPanel()"></div>

<div id="notif-panel">
    <!-- Nagłówek -->
    <div id="notif-panel-header">
        <h2>🔔 Powiadomienia</h2>
        <div class="notif-header-actions">
            <button class="notif-header-btn" onclick="markAllRead()" title="Oznacz wszystkie jako przeczytane">✓ Przeczytane</button>
            <button class="notif-header-btn" onclick="clearAllNotifs()" title="Wyczyść całą historię">🗑 Wyczyść</button>
            <button class="notif-header-btn notif-close-btn" onclick="closeNotifPanel()" title="Zamknij">✕</button>
        </div>
    </div>

    <!-- Filtry -->
    <div id="notif-filter-bar">
        <button class="notif-filter-btn active" data-filter="all"     onclick="setNotifFilter('all')">Wszystkie</button>
        <button class="notif-filter-btn"        data-filter="chat"    onclick="setNotifFilter('chat')">💬 Chat</button>
        <button class="notif-filter-btn"        data-filter="system"  onclick="setNotifFilter('system')">⚙️ System</button>
        <button class="notif-filter-btn"        data-filter="warning" onclick="setNotifFilter('warning')">⚠️ Ostrzeżenia</button>
        <button class="notif-filter-btn"        data-filter="info"    onclick="setNotifFilter('info')">ℹ️ Info</button>
    </div>

    <!-- Lista powiadomień -->
    <div id="notif-list"></div>

    <!-- Stan pusty -->
    <div id="notif-empty-state">
        <div class="empty-icon">🔕</div>
        <p>Brak powiadomień</p>
        <small>Tutaj pojawią się Twoje powiadomienia systemowe i wiadomości.</small>
    </div>

    <!-- Stopka -->
    <div id="notif-panel-footer">
        PingOS Centrum Powiadomień &nbsp;·&nbsp; <span id="notif-count-footer">0</span> powiadomień w historii
    </div>
</div>

<!-- Kontener na toasty -->
<div id="toast-container"></div>

<div id="bsod">
    <h1 style="font-size: 100px; margin:0;">:(</h1>
    <h2 style="font-size: 24px;">System napotkał błąd zasobów.</h2>
    <p id="bsod-progress">0% kompletne</p>
</div>

<div id="screensaver">
    <div id="screensaver-content">
        <span id="pingwin-spinner">🐧</span>
    </div>
</div>

<div id="shutdown-screen">
    <h1 id="shutdown-msg" style="font-size: 28px;">Trwa zamykanie systemu...</h1>
</div>

<div id="fullscreen-overlay">
    <h1>WYMAGANY PEŁNY EKRAN</h1>
    <p>Aby korzystać z PingOS na monitorze,<br>naciśnij klawisz <strong>F11</strong>.</p>
</div>

<script>

// Sprawdzenie ustawienia zmniejszonej przezroczystości przy starcie
if (localStorage.getItem('sys_reduce_transparency') === 'true') {
    document.body.classList.add('reduce-transparency');
}

const currentUser = <?php echo isset($_SESSION['user']) ? 


json_encode(htmlspecialchars($_SESSION['user'])) : '""'; ?>;
// --- BLOKADA PRAWEGO PRZYCISKU MYSZY ---
document.addEventListener('contextmenu', event => event.preventDefault());

function stopScreensaver() {
    if (screensaverActive) {
        const sc = document.getElementById('screensaver');
        if (sc) {
            sc.style.display = 'none'; // Ukrywa wygaszacz
        }
        screensaverActive = false; // Resetuje status
    }
}

// --- APLIKOWANIE PERSONALIZACJI ---
function applyPersonalization() {
    // 1. Barwa Systemu
    const accent = localStorage.getItem('sys_accent_color') || '#0078d4';
    document.documentElement.style.setProperty('--accent-color', accent);

    // 2. Kursor
    const cursor = localStorage.getItem('sys_cursor_style') || 'default';
    document.body.style.cursor = cursor;

    // 3. Tryb Ciemny (BETA)
    if(localStorage.getItem('sys_dark_mode') === 'true') {
        document.body.classList.add('dark-mode');
    } else {
        document.body.classList.remove('dark-mode');
    }

    // 4. Tapeta i Pasek
    const savedBG = localStorage.getItem('webos_wallpaper');
    if(savedBG) document.body.style.backgroundImage = `url('${savedBG}')`;

    const savedOp = localStorage.getItem('sys_taskbar_op');
    if(savedOp && document.getElementById('taskbar')) {
        document.getElementById('taskbar').style.backgroundColor = `rgba(243, 243, 243, ${savedOp})`;
        // Jeśli tryb ciemny, nadpisujemy kolor bazy paska
        if(localStorage.getItem('sys_dark_mode') === 'true') {
            document.getElementById('taskbar').style.backgroundColor = `rgba(20, 20, 20, ${savedOp})`;
        }
    }
}

const userSpecs = { 
    ram: parseFloat(localStorage.getItem('sys_ram_limit')) || <?php echo (float)$userSpecs['ram']; ?>, 
    cpu: parseFloat(localStorage.getItem('sys_cpu_limit')) || <?php echo (float)$userSpecs['cpu']; ?> 
};

document.addEventListener('mousemove', (e) => {
    window.requestAnimationFrame(() => {
        const targets = document.querySelectorAll('#taskbar, #start-menu, .window-header');
        
        targets.forEach(target => {
            const rect = target.getBoundingClientRect();
            // Sprawdzamy czy kursor jest w ogóle w pobliżu elementu
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            target.style.setProperty('--glow-x', `${x}px`);
            target.style.setProperty('--glow-y', `${y}px`);
        });
    });
});

// --- LOGIKA POWIADOMIEŃ W TLE ---
let lastMessageCount = -1;
async function checkNewMessages() {
    const chatWindow = document.getElementById('win-chat');
    const isChatOpen = chatWindow && chatWindow.style.display !== 'none';
    try {
        const r = await fetch('chat_engine.php?action=get&t=' + Date.now());
        const data = await r.json();
        const messages = data.messages || [];
        if (lastMessageCount === -1) { lastMessageCount = messages.length; return; }
        if (messages.length > lastMessageCount) {
            const newMsgs = messages.slice(lastMessageCount);
            if (!isChatOpen) {
                newMsgs.forEach(msg => {
                    if (msg.user !== currentUser) {
                        const isPriv = msg.recipient && msg.recipient === currentUser;
                        const txt = msg.text || '';
                        PingOS.notify({
                            type: 'chat',
                            title: (isPriv ? '🔒 Priv — ' : '') + msg.user,
                            message: txt.length > 80 ? txt.substring(0, 80) + '…' : txt,
                            icon: '💬',
                            onClick: () => openApp('chat', '../../../nienstalowane/chat.php', 'Chat Globalny')
                        });
                    }
                });
            }
            lastMessageCount = messages.length;
        }
    } catch (e) { console.error('Chat check error', e); }
}

function getSystemStats() {
    const openWindows = document.querySelectorAll('.window').length;
    let cpuUse = Math.round((openWindows * 25) / (userSpecs.cpu / 2));
    let ramUse = Math.round((openWindows * 20) / (userSpecs.ram / 4));
    if (cpuUse >= 95 || ramUse >= 95) {
        document.body.classList.add('system-frozen');
        setTimeout(triggerBSOD, 1500);
    }
}

function triggerBSOD() {
    const bsod = document.getElementById('bsod');
    bsod.style.display = 'flex';
    let p = 0;
    const itv = setInterval(() => {
        p += Math.floor(Math.random() * 15);
        document.getElementById('bsod-progress').innerText = Math.min(p, 100) + "% kompletne";
        if(p >= 100) { clearInterval(itv); location.reload(); }
    }, 300);
}

function systemRestart() {
    document.body.style.opacity = "0";
    setTimeout(() => { location.reload(); }, 500);
}

function systemShutdown() {
    const screen = document.getElementById('shutdown-screen');
    const msg = document.getElementById('shutdown-msg');
    closeStartMenu();
    screen.style.display = 'flex';
    setTimeout(() => { msg.innerText = "Zapisywanie danych..."; }, 1000);
    setTimeout(() => {
        msg.innerHTML = "TERAZ MOŻNA BEZPIECZNIE<br>ZAMKNĄĆ PRZEGLĄDARKĘ";
    }, 3500);
}

function auth(action) {
    const user = document.getElementById('u-name')?.value || '';
    const pass = document.getElementById('u-pass')?.value || '';
    const fd = new FormData();
    fd.append('action', action); fd.append('user', user); fd.append('pass', pass);
    fetch('auth.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        if(res.status === 'ok') location.reload(); else alert(res.msg);
    });
}

function toggleStartMenu(e) {
    if (e) e.stopPropagation();
    const m = document.getElementById('start-menu');
    const startBtn = e ? e.currentTarget : document.querySelector('.taskbar-icon.super-btn');
    
    if (!m) return;

    // Sprawdzamy, czy menu jest otwarte (ma klasę opening) i nie jest właśnie zamykane
    if (m.classList.contains('opening') && !m.classList.contains('closing')) {
        // --- ZAMYKANIE ---
        m.classList.add('closing');
        if (startBtn) startBtn.classList.remove('active-start');

        const handleClose = (evt) => {
            if (evt.animationName === 'startMenuPopOut') {
                m.style.display = 'none';
                m.classList.remove('opening', 'closing'); // Czyścimy obie klasy
                m.removeEventListener('animationend', handleClose);
            }
        };
        m.addEventListener('animationend', handleClose);
    } else {
        // --- OTWIERANIE ---
        m.style.display = 'flex';
        m.classList.remove('closing'); 
        
        // Wymuszamy reflow, żeby przeglądarka "zauważyła" zmianę przed dodaniem klasy animacji
        void m.offsetWidth; 
        
        m.classList.add('opening');
        if (startBtn) startBtn.classList.add('active-start');
    }
}

function closeStartMenu() {
    const m = document.getElementById('start-menu');
    const startBtn = document.querySelector('.taskbar-icon.active-start');

    if (m && m.classList.contains('opening') && !m.classList.contains('closing')) {
        m.classList.add('closing');
        if (startBtn) startBtn.classList.remove('active-start');

        const handleClose = (evt) => {
            if (evt.animationName === 'startMenuPopOut') {
                m.style.display = 'none';
                m.classList.remove('opening', 'closing');
                m.removeEventListener('animationend', handleClose);
            }
        };
        m.addEventListener('animationend', handleClose);
    }
}



window.refreshDesktop = function() {
    const d = document.getElementById('desktop');
    const s = document.getElementById('start-apps-list');
    if(!d || !s) return;
    d.innerHTML = ''; s.innerHTML = '';
    fetch('get_installed_info2.php').then(r => r.json()).then(apps => {
        for (const [id, info] of Object.entries(apps)) {
            let appPath = info.filename.startsWith('../../') ? info.filename : `Users/${currentUser}/apps/${info.filename}`;
            const iconEmoji = info.icon || '📄';
            const displayName = info.name || id;
            const icon = document.createElement('div');
            icon.className = "desktop-icon";
            icon.innerHTML = `<div>${iconEmoji}</div><div>${displayName}</div>`;
            // DODAJ TO:
icon.oncontextmenu = (e) => {
    e.preventDefault();
    e.stopPropagation();
    const menu = document.getElementById('app-context-menu');
    menu.style.display = 'block';
    menu.style.left = e.pageX + 'px';
    menu.style.top = e.pageY + 'px';
    
    // Zapamiętaj ID aplikacji dla akcji menu
    menu.dataset.targetApp = id; 
};

icon.ondblclick = () => openApp(id.replace('.php',''), appPath, displayName);
            icon.ondblclick = () => openApp(id.replace('.php',''), appPath, displayName);
            d.appendChild(icon);
            const sitm = document.createElement('div');
            sitm.className = 'start-app-item';
            sitm.innerHTML = `<div>${iconEmoji}</div><div style="font-size:11px;">${displayName}</div>`;
            sitm.onclick = () => { openApp(id.replace('.php',''), appPath, displayName); closeStartMenu(); };
            s.appendChild(sitm);
        }
    });
}

window.updateDesktopDimming = function() {
    const desktop = document.getElementById('desktop');
    if(!desktop) return;

    // Pobieramy ustawienie z localStorage. 
    // Jeśli nie istnieje, zakładamy true (włączone).
    const isDimmingEnabled = localStorage.getItem('sys_dimming_enabled') !== 'false';

    // Liczymy okna, które są widoczne (nie mają display: none)
    const activeWindows = Array.from(document.querySelectorAll('.window'))
                               .filter(win => win.style.display !== 'none').length;

    // Przyciemniamy tylko jeśli funkcja jest WŁĄCZONA w ustawieniach ORAZ są otwarte okna
    if (isDimmingEnabled && activeWindows > 0) {
        desktop.style.backgroundColor = "rgba(0, 0, 0, 0.5)";
    } else {
        // W przeciwnym razie tło jest przezroczyste (widać tapetę w pełnej okazałości)
        desktop.style.backgroundColor = "transparent";
    }
}

let zIdx = 100;
// Pomocnicza mapa przechowująca ikony aplikacji
const openAppsIcons = {};

function focusWindow(winId) {
    const win = typeof winId === 'string' ? document.getElementById(winId) : winId;
    if (!win) return;

    // Usuwamy klasę active ze wszystkich okien
    document.querySelectorAll('.window').forEach(w => {
        w.classList.remove('active');
        w.style.border = "1px solid #ccc"; // Wizualne uśpienie
    });

    // Aktywujemy wybrane okno
    win.classList.add('active');
    win.style.zIndex = ++zIdx;
    win.style.border = "1px solid #0078d7"; // Wizualne wyróżnienie
    
    if (typeof updateTaskbar === 'function') updateTaskbar();
}


async function openApp(id, file, title) {
    // --- MECHANIZM CZARNEJ LISTY ---
    try {
        const response = await fetch('lista.txt');
        if (response.ok) {
            const listContent = await response.text();
            const lines = listContent.split('\n');
            
            // Szukamy, czy nazwa pliku (np. aplikacja.php) jest na liście
            const fileName = file.split('/').pop(); // Wyciąga samą nazwę pliku ze ścieżki
            const index = lines.findIndex(line => line.trim() === fileName);

            if (index !== -1) {
                // Pobieramy opis zagrożenia (następna linia po nazwie pliku)
                const warningMsg = lines[index + 1] || "Brak dodatkowego opisu zagrożenia.";
                
                const confirmRun = await PingOS.prompt(
                    `UWAGA! Ta aplikacja jest na czarnej liście:\n\n"${warningMsg}"\n\nCzy na pewno chcesz ją uruchomić? Wpisz 'TAK', aby kontynuować.`, 
                    "", 
                    "Ostrzeżenie bezpieczeństwa"
                );

                if (confirmRun !== "TAK") {
                    console.warn("Uruchomienie zablokowane przez użytkownika: " + fileName);
                    return; // Przerywamy otwieranie aplikacji
                }
            }
        }
    } catch (e) {
        console.error("Nie udało się sprawdzić czarnej listy, kontynuuję bezpiecznie...");
    }

    // --- DALSZA CZĘŚĆ ORYGINALNEJ FUNKCJI ---
    const winId = 'win-' + id;
    const existingWin = document.getElementById(winId);
    // ... reszta Twojego kodu openApp ...
    
    if(existingWin) {
        if (existingWin.style.display === 'none') {
            existingWin.style.display = 'flex';
            focusWindow(existingWin); // --- MIEJSCE 1: Przywracanie okna ---
            window.updateDesktopDimming(); 
        } else {
            // Jeśli okno jest na wierzchu i jest aktywne - minimalizuj
            if(existingWin.classList.contains('active')) {
                minimizeWindow(winId);
            } else {
                // Jeśli jest otwarte, ale nieaktywne - tylko je aktywuj
                focusWindow(existingWin);
            }
        }
        return;
    }

// 1. Definiujemy domyślne wymiary
    let winWidth = '800px';
    let winHeight = '550px';

    // 2. Próbujemy pobrać wymiary z pliku PHP
    try {
        const response = await fetch(file);
        const text = await response.text();
        // Szukamy wzorca: ROZDIELCZOSC X,Y
        const match = text.match(/ROZDIELCZOSC\s+(\d+)\s*,\s*(\d+)/);
        if (match) {
            winWidth = match[1] + 'px';
            winHeight = match[2] + 'px';
        }
    } catch (e) {
        console.error("Nie udało się odczytać rozdzielczości:", e);
    }

    // 3. Tworzymy okno z użyciem pobranych (lub domyślnych) wartości
    const win = document.createElement('div');
    win.className = 'window';
    win.id = winId;
    win.dataset.title = title;
    win.dataset.appId = id;
    
    // Podstawiamy zmienne zamiast sztywnych wartości
    win.style.width = winWidth; 
    win.style.height = winHeight;
    
    win.style.top = (50 + (zIdx % 10) * 25) + 'px';
    win.style.left = (150 + (zIdx % 10) * 25) + 'px';
    win.style.display = 'flex';

    win.addEventListener('mousedown', () => focusWindow(winId));

    const desktopIcons = document.querySelectorAll('.desktop-icon');
    let appEmoji = '📄';
    desktopIcons.forEach(icon => {
        if(icon.innerText.includes(title)) appEmoji = icon.querySelector('div').innerText;
    });
    win.dataset.icon = appEmoji;

    win.innerHTML = `
        <div class="window-header" onmousedown="dragStart(event, '${winId}')" ondblclick="toggleMaximize('${winId}')">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size:13px; font-weight:600; color:#444;">${title}</span>
                <button onclick="refreshWindowContent('${winId}', '${file}')" 
                        style="background:none; border:none; cursor:pointer; font-size:12px; padding:0; margin:0;" 
                        title="Odśwież zawartość">🔃</button>
            </div>
            <div class="win-controls">
                <span style="background:#28c940;" onclick="event.stopPropagation(); minimizeWindow('${winId}')" title="Minimalizuj"></span>
                <span style="background:#febc2e;" onclick="event.stopPropagation(); toggleMaximize('${winId}')" title="Maksymalizuj"></span>
                <span style="background:#ff5f57;" onclick="event.stopPropagation(); closeApp('${winId}')" title="Zamknij"></span>
            </div>
        </div>
        <div class="window-content" id="content-${winId}"><div style="padding:20px;">Ładowanie...</div></div>
        <div class="resizer" onmousedown="resizeStart(event, '${winId}')"></div>
    `;
    
    document.getElementById('windows-container').appendChild(win);
    refreshWindowContent(winId, file);
    
    focusWindow(win); // --- MIEJSCE 2: Aktywacja nowego okna ---
    
    getSystemStats();
    window.updateDesktopDimming(); 
}


// Funkcja zamykania okna (usuwa też ikonę z paska)
function closeApp(winId) {
    const win = document.getElementById(winId);
    if (!win) return;

    // Dodajemy efekt płynnego znikania przed usunięciem
    win.style.transition = 'all 0.2s ease';
    win.style.opacity = '0';
    win.style.transform = 'scale(0.9)';

    // Czekamy na koniec animacji (200ms), a potem czyścimy DOM
    setTimeout(() => {
        win.remove();
        
        // Odświeżamy pasek zadań
        updateTaskbar();
        
        // Aktualizujemy statystyki (tak jak miałeś w oryginale)
        getSystemStats();

        // KLUCZOWE: Sprawdzamy, czy po zamknięciu tego okna 
        // tło pulpitu powinno się rozjaśnić
        if (typeof window.updateDesktopDimming === 'function') {
            window.updateDesktopDimming();
        }
    }, 200);
}

function minimizeWindow(winId) {
    const win = document.getElementById(winId);
    if (!win) return;
    
    // Rozpoczęcie animacji znikania
    win.classList.add('animating');
    win.style.opacity = '0';
    win.style.transform = 'scale(0.9) translateY(20px)';
    
    setTimeout(() => {
        // Ukrycie okna po zakończeniu animacji
        win.style.display = 'none';
        
        // Reset stylów, aby okno było gotowe do ponownego otwarcia (bez animacji na start)
        win.style.opacity = '1';
        win.style.transform = 'none';
        win.classList.remove('animating');
        
        // Odświeżenie paska zadań
        updateTaskbar();
        
        // --- KLUCZOWA ZMIANA ---
        // Sprawdzamy, czy po zminimalizowaniu tego okna 
        // na pulpicie zostały inne widoczne okna.
        if (typeof window.updateDesktopDimming === 'function') {
            window.updateDesktopDimming();
        }
    }, 200);
}

const PingOS = {
    // Odpowiednik alert()
    alert: function(message, title = "System") {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'sys-dialog-overlay';
            overlay.innerHTML = `
                <div class="sys-dialog">
                    <div class="sys-dialog-header">⚠️ ${title}</div>
                    <div class="sys-dialog-body">${message}</div>
                    <div class="sys-dialog-footer">
                        <button class="sys-btn sys-btn-primary" id="dialog-ok">OK</button>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
            document.getElementById('dialog-ok').onclick = () => { overlay.remove(); resolve(); };
        });
    },

    // Odpowiednik prompt()
    prompt: function(message, defaultValue = "", title = "Wejście Systemowe") {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'sys-dialog-overlay';
            overlay.innerHTML = `
                <div class="sys-dialog">
                    <div class="sys-dialog-header">⌨️ ${title}</div>
                    <div class="sys-dialog-body">
                        <div>${message}</div>
                        <input type="text" id="dialog-input" class="sys-dialog-input" value="${defaultValue}" autofocus>
                    </div>
                    <div class="sys-dialog-footer">
                        <button class="sys-btn sys-btn-secondary" id="dialog-cancel">Anuluj</button>
                        <button class="sys-btn sys-btn-primary" id="dialog-submit">Potwierdź</button>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
            const input = document.getElementById('dialog-input');
            input.focus();
            input.onkeydown = (e) => { if(e.key === 'Enter') document.getElementById('dialog-submit').click(); };

            document.getElementById('dialog-cancel').onclick = () => { overlay.remove(); resolve(null); };
            document.getElementById('dialog-submit').onclick = () => { 
                const val = input.value; 
                overlay.remove(); 
                resolve(val); 
            };
        });
    }
};

// ================================================================
// CENTRUM POWIADOMIEŃ — PEŁNA LOGIKA JS
// ================================================================

// ---- Magazyn powiadomień (session-persistent) ----
const notifStore = (function() {
    const STORAGE_KEY = 'pingos_notifs_v2';
    const MAX_ITEMS   = 60;
    let items  = [];
    let filter = 'all';

    function save() {
        try {
            // Serializujemy bez onClick (nie można serializować funkcji)
            const payload = items.map(n => { const c = {...n}; delete c.onClick; return c; });
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch(e) {}
    }

    function load() {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            if (raw) items = JSON.parse(raw) || [];
        } catch(e) { items = []; }
    }

    load(); // Załaduj od razu przy inicjalizacji

    return {
        get items()  { return items; },
        get filter() { return filter; },
        set filter(v){ filter = v; },

        add(notif) {
            items.unshift(notif);
            if (items.length > MAX_ITEMS) items.length = MAX_ITEMS;
            save();
        },

        remove(id) {
            items = items.filter(n => n.id !== id);
            save();
            // Poinformuj backend (best-effort)
            const fd = new FormData();
            fd.append('action', 'dismiss');
            fd.append('id', id);
            fetch('notification_engine.php', { method: 'POST', body: fd }).catch(() => {});
        },

        markAllRead() {
            items.forEach(n => n.read = true);
            save();
        },

        clearAll() {
            items = [];
            save();
            fetch('notification_engine.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'clear_all' })
            }).catch(() => {});
        },

        getFiltered() {
            return filter === 'all' ? items : items.filter(n => n.type === filter);
        },

        unreadCount() {
            return items.filter(n => !n.read).length;
        },

        async loadFromServer() {
            try {
                const response = await fetch('notification_engine.php?action=get');
                if (response.ok) {
                    const data = await response.json();
                    if (data.status === 'ok' && Array.isArray(data.notifications)) {
                        items = data.notifications;
                        save();
                        updateNotifBadge();
                        renderNotifPanel();
                    }
                }
            } catch(e) {
                console.error("Failed to load notifications from server:", e);
            }
        },

        save
    };
})();

// ---- Globalna funkcja PingOS.notify() ----
PingOS.notify = function(options = {}) {
    const {
        type     = 'info',
        title    = 'Powiadomienie',
        message  = '',
        icon     = null,
        onClick  = null,
        duration = 6000
    } = options;

    const typeIcons = { chat:'💬', system:'⚙️', warning:'⚠️', info:'ℹ️', error:'❌' };
    const notifIcon  = icon || typeIcons[type] || '🔔';
    const now        = new Date();
    const timeStr    = now.toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' });
    const dateStr    = now.toLocaleDateString('pl-PL', { day: 'numeric', month: 'long', year: 'numeric' });

    const notif = {
        id:        'n_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6),
        type, title, message,
        icon:      notifIcon,
        time:      timeStr,
        date:      dateStr,
        timestamp: now.getTime(),
        read:      false,
        onClick
    };

    notifStore.add(notif);
    updateNotifBadge();

    // Toast tylko gdy panel jest zamknięty
    const panel = document.getElementById('notif-panel');
    if (!panel || !panel.classList.contains('open')) {
        showToast(notif, duration);
    } else {
        // Panel otwarty — tylko re-renderuj listę
        renderNotifPanel();
    }

    // Dźwięk dla chatu
    if (type === 'chat') {
        const snd = document.getElementById('chat-sound');
        if (snd) { snd.currentTime = 0; snd.play().catch(() => {}); }
    }

    // Wyślij do backendu (best-effort)
    const fd = new FormData();
    fd.append('action',  'add');
    fd.append('type',    type);
    fd.append('title',   title);
    fd.append('message', message);
    fd.append('icon',    notifIcon);
    fetch('notification_engine.php', { method: 'POST', body: fd }).catch(() => {});

    return notif.id;
};

// ---- Toast: wyskakujące powiadomienie ----
function showToast(notif, duration) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className   = 'toast-notif';
    toast.dataset.type = notif.type;
    toast.dataset.id   = notif.id;

    const hasProgress = duration > 0 && duration < 60000;
    toast.innerHTML = `
        <div class="toast-top">
            <span class="toast-icon">${notif.icon}</span>
            <span class="toast-title">${notif.title}</span>
            <button class="toast-close" onclick="dismissToast('${notif.id}')" title="Zamknij">✕</button>
        </div>
        ${notif.message ? `<div class="toast-message">${notif.message}</div>` : ''}
        ${hasProgress   ? `<div class="toast-progress" id="tp-${notif.id}"></div>` : ''}
    `;

    // Klikalne toasty (otwierają powiązaną akcję)
    if (notif.onClick) {
        toast.style.cursor = 'pointer';
        toast.addEventListener('click', e => {
            if (!e.target.classList.contains('toast-close')) {
                notif.onClick();
                dismissToast(notif.id);
            }
        });
    }

    container.appendChild(toast);

    // Animacja paska postępu
    if (hasProgress) {
        const bar = document.getElementById('tp-' + notif.id);
        if (bar) {
            bar.style.transition = `width ${duration}ms linear`;
            void bar.offsetWidth; // reflow
            bar.style.width = '0%';
        }
        setTimeout(() => dismissToast(notif.id), duration);
    }
}

function dismissToast(id) {
    const toast = document.querySelector(`.toast-notif[data-id="${id}"]`);
    if (!toast) return;
    toast.classList.add('toast-hiding');
    setTimeout(() => toast.remove(), 300);
}

// ---- Zarządzanie panelem ----
function toggleNotifPanel(e) {
    if (e) e.stopPropagation();
    const panel = document.getElementById('notif-panel');
    if (!panel) return;
    panel.classList.contains('open') ? closeNotifPanel() : openNotifPanel();
}

function openNotifPanel() {
    const panel   = document.getElementById('notif-panel');
    const overlay = document.getElementById('notif-overlay');
    if (!panel) return;
    // Oznacz jako przeczytane przy otwarciu
    notifStore.markAllRead();
    panel.classList.add('open');
    if (overlay) overlay.classList.add('active');
    updateNotifBadge();
    renderNotifPanel();
    if (typeof closeStartMenu === 'function') closeStartMenu();
}

function closeNotifPanel() {
    const panel   = document.getElementById('notif-panel');
    const overlay = document.getElementById('notif-overlay');
    if (panel)   panel.classList.remove('open');
    if (overlay) overlay.classList.remove('active');
}

function setNotifFilter(f) {
    notifStore.filter = f;
    document.querySelectorAll('.notif-filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === f);
    });
    renderNotifPanel();
}

function markAllRead() {
    notifStore.markAllRead();
    updateNotifBadge();
    renderNotifPanel();
    fetch('notification_engine.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'mark_read' })
    }).catch(() => {});
}

function clearAllNotifs() {
    notifStore.clearAll();
    updateNotifBadge();
    renderNotifPanel();
}

function dismissNotif(id) {
    const item = document.querySelector(`.notif-item[data-id="${id}"]`);
    if (item) {
        item.style.opacity   = '0';
        item.style.transform = 'translateX(30px)';
        setTimeout(() => { notifStore.remove(id); updateNotifBadge(); renderNotifPanel(); }, 260);
    } else {
        notifStore.remove(id);
        updateNotifBadge();
        renderNotifPanel();
    }
}

function handleNotifClick(id) {
    const notif = notifStore.items.find(n => n.id === id);
    if (!notif) return;
    notif.read = true;
    notifStore.save();
    updateNotifBadge();
    if (typeof notif.onClick === 'function') notif.onClick();
    closeNotifPanel();
}

function updateNotifBadge() {
    const badge = document.getElementById('notif-badge');
    const bell  = document.getElementById('notif-bell');
    if (!badge) return;
    const cnt = notifStore.unreadCount();
    badge.textContent = cnt > 9 ? '9+' : String(cnt);
    if (bell) bell.classList.toggle('has-notif', cnt > 0);
}

function renderNotifPanel() {
    const list       = document.getElementById('notif-list');
    const emptyState = document.getElementById('notif-empty-state');
    const footer     = document.getElementById('notif-count-footer');
    if (!list) return;

    const filtered = notifStore.getFiltered();

    if (filtered.length === 0) {
        list.style.display = 'none';
        if (emptyState) emptyState.style.display = 'flex';
    } else {
        list.style.display = 'flex';
        if (emptyState) emptyState.style.display = 'none';

        const today     = new Date().toDateString();
        const yesterday = new Date(Date.now() - 86400000).toDateString();
        let html      = '';
        let lastLabel = '';

        filtered.forEach((notif, i) => {
            // Etykieta grupy daty
            const ds = new Date(notif.timestamp).toDateString();
            let label = ds === today     ? 'Ostatnio'
                      : ds === yesterday ? 'Wczoraj'
                      : notif.date || ds;

            if (label !== lastLabel) {
                html += `<div class="notif-group-label">${label}</div>`;
                lastLabel = label;
            }

            const readClass  = notif.read ? '' : ' unread';
            const actionBtn  = notif.onClick
                ? `<button class="notif-action-btn" onclick="handleNotifClick('${notif.id}')">Otwórz →</button>`
                : '';

            html += `
            <div class="notif-item${readClass}" data-id="${notif.id}" data-type="${notif.type}"
                 style="animation-delay:${Math.min(i * 0.035, 0.4)}s">
                <div class="notif-item-top">
                    <span class="notif-icon">${notif.icon}</span>
                    <span class="notif-title">${notif.title}</span>
                    <span class="notif-time">${notif.time}</span>
                    <button class="notif-dismiss" onclick="dismissNotif('${notif.id}')" title="Odrzuć">✕</button>
                </div>
                ${notif.message ? `<div class="notif-message">${notif.message}</div>` : ''}
                ${actionBtn}
            </div>`;
        });

        list.innerHTML = html;
    }

    if (footer) footer.textContent = notifStore.items.length;
}

// Renderuj przy starcie (odtworzone z sessionStorage) i pobierz z serwera
updateNotifBadge();
renderNotifPanel();
notifStore.loadFromServer();

// ---- Skrót klawiaturowy: ALT + N ----
// (Dodane do istniejącego listenera keydown niżej w kodzie)
window._notifAltN = true; // flaga, żeby nie dublować listenera

// KLUCZOWA FUNKCJA: Aktualizacja paska zadań
function updateTaskbar() {
    const container = document.getElementById('taskbar-apps');
    if(!container) return;
    container.innerHTML = '';
    
    const windows = document.querySelectorAll('.window');
    windows.forEach(win => {
        const winId = win.id;
        const iconBtn = document.createElement('div');
        iconBtn.className = 'taskbar-app-icon';
        if (win.style.display !== 'none') iconBtn.classList.add('active');
        
        iconBtn.innerHTML = win.dataset.icon || '📄';
        iconBtn.title = win.dataset.title;
        
        iconBtn.onclick = () => {
            if (win.style.display === 'none') {
                win.style.display = 'flex';
                win.style.zIndex = ++zIdx;
            } else {
                minimizeWindow(winId);
            }
            updateTaskbar();
        };
        container.appendChild(iconBtn);
    });
}

function refreshWindowContent(winId, file) {
    fetch('get_app_content.php?file=' + file).then(r => r.text()).then(html => {
        const cont = document.getElementById('content-' + winId);
        cont.innerHTML = html;
        const scripts = cont.querySelectorAll('script');
        scripts.forEach(s => {
            const ns = document.createElement("script");
            ns.text = s.text;
            document.head.appendChild(ns).parentNode.removeChild(ns);
        });
    });
}

function toggleMaximize(winId) {
    const win = document.getElementById(winId);
    if (!win) return;
    win.classList.add('animating');
    win.classList.toggle('maximized');
    setTimeout(() => win.classList.remove('animating'), 350);
}

function dragStart(e, id) {
    currentWinFocusIdx = -1; // Resetujemy indeks przełączania po kliknięciu myszką
    const win = document.getElementById(id);
    if(win.classList.contains('maximized')) return;
    win.style.zIndex = ++zIdx;
    win.classList.remove('animating');
    let sX = e.clientX - win.getBoundingClientRect().left;
    let sY = e.clientY - win.getBoundingClientRect().top;
    function move(ev) {
        win.style.left = ev.pageX - sX + 'px';
        win.style.top = ev.pageY - sY + 'px';
    }
    document.addEventListener('mousemove', move);
    document.onmouseup = () => {
        document.removeEventListener('mousemove', move);
        document.onmouseup = null;
    };
}

function resizeStart(e, id) {
    e.preventDefault();
    e.stopPropagation(); // Zapobiega przeciąganiu okna podczas zmiany rozmiaru
    
    const win = document.getElementById(id);
    if(win.classList.contains('maximized')) return;
    
    win.style.zIndex = ++zIdx;
    
    let startWidth = win.offsetWidth;
    let startHeight = win.offsetHeight;
    let startX = e.clientX;
    let startY = e.clientY;

    function doResize(ev) {
        // Obliczamy nowy rozmiar
        const newWidth = startWidth + (ev.clientX - startX);
        const newHeight = startHeight + (ev.clientY - startY);
        
        // Minimalne wymiary okna
        if (newWidth > 200) win.style.width = newWidth + 'px';
        if (newHeight > 150) win.style.height = newHeight + 'px';
    }

    function stopResize() {
        document.removeEventListener('mousemove', doResize);
        document.removeEventListener('mouseup', stopResize);
        // Po zmianie rozmiaru warto odświeżyć statystyki lub wymiary canvasu jeśli to Paint
        window.dispatchEvent(new Event('resize')); 
    }

    document.addEventListener('mousemove', doResize);
    document.addEventListener('mouseup', stopResize);
}

function updateWallpaperFocus() {
    const desktop = document.getElementById('desktop');
    if (!desktop) return;

    // Pobieramy wszystkie okna, które NIE są ukryte (display != 'none')
    const openWindows = Array.from(document.querySelectorAll('.window')).filter(win => win.style.display !== 'none');

    if (openWindows.length > 0) {
        // Jeśli są otwarte okna, przyciemniamy pulpit
        desktop.style.backgroundColor = "rgba(0, 0, 0, 0.4)"; 
    } else {
        // Jeśli nie ma okien, tapeta wraca do pełnej jasności
        desktop.style.backgroundColor = "rgba(0, 0, 0, 0)";
    }
}

let lastHighResourceNotif = 0;
function getSystemStats() {
    const openWindows = document.querySelectorAll('.window').length;
    let cpuUse = Math.round((openWindows * 25) / (userSpecs.cpu / 2));
    let ramUse = Math.round((openWindows * 20) / (userSpecs.ram / 4));
    if (cpuUse >= 95 || ramUse >= 95) {
        document.body.classList.add('system-frozen');
        setTimeout(triggerBSOD, 1500);
    } else if ((cpuUse >= 70 || ramUse >= 70) && (Date.now() - lastHighResourceNotif > 30000)) {
        lastHighResourceNotif = Date.now();
        PingOS.notify({
            type:    'warning',
            title:   'Wysokie zużycie zasobów',
            message: `CPU: ${Math.min(cpuUse,100)}% │ RAM: ${Math.min(ramUse,100)}% — Rozważ zamknięcie części okien.`,
            icon:    '⚠️',
            duration: 8000
        });
    }
    return { cpu: Math.min(cpuUse, 100), ram: Math.min(ramUse, 100) };
}

(function() {
    function systemPing() {
        // Uderzamy do active_engine.php zamiast fs.php
        fetch('active_engine.php?action=ping').catch(() => {});
    }
    systemPing(); 
    setInterval(systemPing, 20000); // Co 20 sekund wystarczy
})();

// Wysyłanie pingu co 15 sekund
setInterval(function() {
    fetch('active_engine.php?action=ping').catch(() => {});
}, 15000);

// Sygnał "Ostatniej Woli" - wysyłany natychmiast przy zamykaniu karty
window.addEventListener('beforeunload', function() {
    // navigator.sendBeacon gwarantuje wysłanie żądania nawet po zamknięciu okna
    navigator.sendBeacon('active_engine.php?action=logout_signal');
});

let currentWinFocusIdx = -1;

// --- FUNKCJA WYSZUKIWANIA I URUCHAMIANIA ---
async function runCommand() {
    const input = await PingOS.prompt("Wpisz nazwę aplikacji lub ID:", "", "Szybkie uruchamianie");
    
    if (!input) return;

    const query = input.toLowerCase().trim();
    
    // Pobieramy aktualną listę aplikacji z serwera
    fetch('get_installed_info2.php')
        .then(r => r.json())
        .then(apps => {
            let foundAppId = null;
            let foundAppPath = null;
            let foundAppName = null;

            for (const [id, info] of Object.entries(apps)) {
                const name = (info.name || "").toLowerCase();
                const appId = id.toLowerCase().replace('.php', '');

                // Szukamy dopasowania po ID lub nazwie
                if (appId === query || name === query) {
                    foundAppId = id.replace('.php', '');
                    foundAppPath = info.filename.startsWith('../../') ? info.filename : `Users/${currentUser}/apps/${info.filename}`;
                    foundAppName = info.name || id;
                    break;
                }
            }

            if (foundAppId) {
                openApp(foundAppId, foundAppPath, foundAppName);
            } else {
                PingOS.alert(`Nie znaleziono aplikacji: "${input}"`, "Błąd uruchamiania");
            }
        });
}




// --- 1. FUNKCJA ZARZĄDZANIA AKTYWNOŚCIĄ OKIEN ---
// Dodaj to na początku sekcji ze skryptami
function focusWindow(win) {
    // Usuń klasę active ze wszystkich okien
    document.querySelectorAll('.window').forEach(w => {
        w.classList.remove('active');
        w.style.boxShadow = "none"; // Opcjonalnie: usuń cień z nieaktywnych
    });

    // Dodaj klasę active i wyciągnij okno na wierzch
    win.classList.add('active');
    if (typeof zIdx !== 'undefined') {
        win.style.zIndex = ++zIdx;
    }
    win.style.boxShadow = "0 10px 30px rgba(0,0,0,0.5)"; // Wyróżnienie aktywnego
    
    if (typeof updateTaskbar === 'function') updateTaskbar();
}

// --- 2. FUNKCJA SZYBKIEGO URUCHAMIANIA ---
async function runCommand() {
    const input = await PingOS.prompt("Wpisz nazwę aplikacji lub ID:", "", "Szybkie uruchamianie");
    if (!input) return;

    const query = input.toLowerCase().trim();
    fetch('get_installed_info2.php')
        .then(r => r.json())
        .then(apps => {
            for (const [id, info] of Object.entries(apps)) {
                const name = (info.name || "").toLowerCase();
                const appId = id.toLowerCase().replace('.php', '');
                if (appId === query || name === query) {
                    openApp(id.replace('.php', ''), 
                            info.filename.startsWith('../../') ? info.filename : `Users/${currentUser}/apps/${info.filename}`, 
                            info.name || id);
                    return;
                }
            }
            PingOS.alert(`Nie znaleziono aplikacji: "${input}"`, "Błąd");
        });
}

function focusWindow(target) {
    const win = typeof target === 'string' ? document.getElementById(target) : target;
    if (!win) return;

    // 1. Zabierz .active wszystkim oknom
    document.querySelectorAll('.window').forEach(w => {
        w.classList.remove('active');
        w.style.boxShadow = "none";
    });

    // 2. Daj .active temu jednemu
    win.classList.add('active');
    win.style.zIndex = ++zIdx;
    win.style.boxShadow = "0 10px 30px rgba(0,0,0,0.3)";
    
    if (typeof updateTaskbar === 'function') updateTaskbar();
}

// --- 3. GŁÓWNA OBSŁUGA KLAWIATURY ---
document.addEventListener('keydown', function(e) {
    if (!e.altKey) return; 

    const key = e.key.toLowerCase();

    // ALT + W (ZAMKNIĘCIE)
    if (key === 'w') {
        e.preventDefault();
        const activeWin = document.querySelector('.window.active');
        if (activeWin && typeof closeApp === 'function') {
            closeApp(activeWin.id);
        }
    }

    // ALT + E (MAKSYMALIZACJA)
    if (key === 'e') {
        e.preventDefault();
        const activeWin = document.querySelector('.window.active');
        if (activeWin && typeof toggleMaximize === 'function') {
            toggleMaximize(activeWin.id);
        }
    }

    // ALT + S (SZUKAJ)
    if (key === 's') {
        e.preventDefault();
        if (typeof runCommand === 'function') runCommand();
    }

    // ALT + X (START) - Sprawdza "Pingwin" lub "Start"
    if (key === 'x') {
        e.preventDefault();
        const startBtn = document.querySelector('.taskbar-icon[title="Pingwin"]') || 
                         document.querySelector('.taskbar-icon[title="Start"]');
        if (startBtn) toggleStartMenu({ stopPropagation: () => {}, currentTarget: startBtn });
    }

    // ALT + Z (PRZEŁĄCZANIE)
    if (key === 'z') {
        e.preventDefault();
        const windows = Array.from(document.querySelectorAll('.window'));
        if (windows.length <= 1) return;
        
        // Sortujemy po zIndex
        windows.sort((a, b) => parseInt(a.style.zIndex || 0) - parseInt(b.style.zIndex || 0));
        
        // Wybieramy okno "pod" aktualnym
        let targetWin = windows[windows.length - 2];
        if (targetWin) {
            if (targetWin.style.display === 'none') targetWin.style.display = 'flex';
            focusWindow(targetWin);
        }
    }

    // ALT + P (UKRYWANIE/POKAZYWANIE PINGWINKA)
    if (key === 'p') {
        e.preventDefault();
        const penguin = document.getElementById('wallpaper-penguin');
        if (penguin) {
            penguin.style.display = (penguin.style.display === 'none') ? 'block' : 'none';
        }
    }
});





// --- LOGIKA ZEGARA ---
function updateClock() {
    const clockElement = document.getElementById('system-clock');
    if (clockElement) {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        clockElement.innerText = `${hours}:${minutes}:${seconds}`;
    }
}

// Uruchomienie zegara co sekundę
setInterval(updateClock, 1100);
updateClock(); // Wywołanie natychmiastowe, żeby nie czekać sekundy na start




// Inicjalizacja przy starcie
<?php if(isset($_SESSION['user'])): ?>
    applyPersonalization();
    window.refreshDesktop();
    setInterval(getSystemStats, 3000);
    setInterval(checkNewMessages, 3000);

    // Powiadomienie powitalne (po załadowaniu pulpitu)
    setTimeout(() => {
        const hour = new Date().getHours();
        const greeting = hour < 12 ? 'Dzień dobry' : hour < 18 ? 'Dzień dobry' : 'Dobry wieczór';
        PingOS.notify({
            type:     'system',
            title:    `${greeting}, ${currentUser}!`,
            message:  'Sprawdź, czy nie masz nowych wiadomości na czacie.',
            icon:     '🐧',
            duration: 25000
        });
    }, 1800);

    // Skrót ALT+N — otwieranie centrum powiadomień
    document.addEventListener('keydown', function(e) {
        if (e.altKey && e.key.toLowerCase() === 'n') {
            e.preventDefault();
            toggleNotifPanel(null);
        }
    });

// LOGIKA ENTER + SPLASH SCREEN
// LOGIKA ENTER + SPLASH SCREEN Z TAJNYM POMINIĘCIEM
(function() {
    const initScreen = document.getElementById('init-screen');
    const splash = document.getElementById('splash-screen');
    const sound = document.getElementById('start-sound');
    
    let spaceCount = 0;
    let spaceTimer = null;

    const skipBoot = () => {
        console.log("PingOS: Boot sequence skipped by user.");
        if(sound) {
            sound.pause();
            sound.currentTime = 0;
        }
        if(splash) splash.remove();
        if(initScreen) initScreen.remove();
        window.removeEventListener('keydown', handleBootKeys);
        // Usuwamy też nasłuchiwanie kliknięć po pominięciu
        if(initScreen) initScreen.removeEventListener('click', handleInitClick);
    };

    const startBootSequence = () => {
        if(initScreen) initScreen.style.display = 'none';
        if(splash) splash.style.display = 'flex';
        if(sound) {
            sound.play().catch(e => console.error("Błąd dźwięku:", e));
        }

        setTimeout(() => {
            if(splash) {
                splash.style.transition = "opacity 2s ease";
                splash.style.opacity = "0";
                setTimeout(() => {
                    if(splash.parentNode) splash.remove();
                    window.removeEventListener('keydown', handleBootKeys);
                }, 2000);
            }
        }, 8000);
    };

    // Funkcja pomocnicza dla kliknięcia/dotyku
    const handleInitClick = () => {
        if (initScreen && initScreen.style.display !== 'none') {
            startBootSequence();
        }
    };

    const handleBootKeys = (e) => {
        if (e.key === 'Enter' && initScreen.style.display !== 'none') {
            startBootSequence();
        }

        if (e.key === ' ') {
            spaceCount++;
            if (spaceCount === 1) {
                spaceTimer = setTimeout(() => {
                    spaceCount = 0;
                }, 3000);
            }
            if (spaceCount === 2) {
                clearTimeout(spaceTimer);
                skipBoot();
            }
        }
    };

    // Rejestracja zdarzeń klawiatury
    window.addEventListener('keydown', handleBootKeys);

    // Rejestracja kliknięcia (dla tabletów i myszki) na całym ekranie startowym
    if (initScreen) {
        initScreen.addEventListener('click', handleInitClick);
        // Opcjonalnie dla bardzo starych tabletów:
        initScreen.addEventListener('touchstart', handleInitClick, {passive: true});
    }
})();

<?php endif; ?>

// --- OBSŁUGA WYGASZACZA EKRANU (Część Logiczna JS) ---
let screensaverActive = false;

function toggleScreensaver() {
    const sc = document.getElementById('screensaver');
    if(!sc) return;

    // Zamykamy Menu Start (jeśli funkcja istnieje)
    if(typeof closeStartMenu === 'function') closeStartMenu(); 
    
    // Włączamy wygaszacz
    sc.style.display = 'block';
    screensaverActive = true;
    
    // Przechodzimy w tryb pełnoekranowy (opcjonalne)
    if (document.documentElement.requestFullscreen) {
        document.documentElement.requestFullscreen().catch((e) => {});
    }
}

// --- LOGIKA WYMUSZANIA F11 ---
// --- LOGIKA WYMUSZANIA F11 Z AUTO-UKRYWANIEM ---
const overlay = document.getElementById('fullscreen-overlay');
let overlayTimeout = null;

function checkFullScreen() {
    // Sprawdzamy czy wysokość okna zgadza się z rozdzielczością ekranu
    const isFull = (window.screen.height - window.innerHeight) < 10; // Zwiększony margines dla mobilnych pasków

    if (isFull) {
        overlay.style.display = 'none';
        if (overlayTimeout) clearTimeout(overlayTimeout);
    } else {
        // Pokazujemy tylko jeśli użytkownik jest na pulpicie
        if (document.getElementById('desktop').style.display !== 'none') {
            overlay.style.display = 'flex';

            // URUCHAMIAMY LICZNIK: Jeśli po 5 sekundach nadal nie ma Fullscreen, ukrywamy komunikat
            if (!overlayTimeout) {
                overlayTimeout = setTimeout(() => {
                    overlay.style.transition = "opacity 1s ease";
                    overlay.style.opacity = "0";
                    setTimeout(() => {
                        overlay.style.display = 'none';
                        overlay.style.opacity = "1"; // Reset opacity na przyszłość
                        overlayTimeout = null;
                    }, 1000);
                }, 5000); // 5 sekund widoczności
            }
        }
    }
}

// Sprawdzaj przy zmianie rozmiaru i przy starcie
window.addEventListener('resize', () => {
    // Resetujemy timeout przy zmianie rozmiaru, żeby sprawdzić stan na nowo
    if (overlayTimeout) {
        clearTimeout(overlayTimeout);
        overlayTimeout = null;
    }
    checkFullScreen();
});

// Wywołaj z lekkim opóźnieniem
setTimeout(checkFullScreen, 1000);


// Nasłuchiwanie zdarzeń
// document.addEventListener('mousemove', stopScreensaver);
document.addEventListener('keydown', stopScreensaver);
document.addEventListener('click', stopScreensaver);

// Ukrywanie menu przy kliknięciu lewym przyciskiem
document.addEventListener('click', () => {
    document.getElementById('app-context-menu').style.display = 'none';
});

// Funkcja wywoływana z menu kontekstowego
function moveIcon() {
    const menu = document.getElementById('app-context-menu');
    const appId = menu.dataset.targetApp;
    
    // Znajdź element ikony na pulpicie
    const desktopIcons = document.querySelectorAll('.desktop-icon');
    let targetElement = null;
    
    // Szukamy ikony, która odpowiada ID aplikacji
    desktopIcons.forEach(icon => {
        if(icon.getAttribute('data-id') === appId) targetElement = icon;
    });

    if(targetElement) {
        PingOS.alert("Tryb przesuwania aktywny dla: " + appId + ". Kliknij w nowe miejsce na pulpicie (symulacja).", "System");
        // Tutaj docelowo można dodać Drag & Drop, ale na razie odblokujmy zmianę kolejności
        targetElement.style.outline = "2px solid var(--accent-color)";
    }
}


function uninstallApp() {
    const menu = document.getElementById('app-context-menu');
    const appId = menu.dataset.targetApp; // To jest nazwa pliku, np. "calc.php"

    const overlay = document.createElement('div');
    overlay.style = "position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:20000; display:flex; align-items:center; justify-content:center; backdrop-filter: blur(8px);";

    const dialog = document.createElement('div');
    dialog.style = "background:#fff; padding:30px; border-radius:15px; box-shadow:0 20px 60px rgba(0,0,0,0.6); width:400px; text-align:center; border: 2px solid #d9534f; font-family: sans-serif;";
    
    dialog.innerHTML = `
        <div style="font-weight:bold; margin-bottom:15px; font-size:22px; color:#222;">ODINSTALOWAĆ?</div>
        <div style="margin-bottom:25px; color:#444; line-height:1.6; font-size:15px;">
            Czy na pewno chcesz usunąć aplikację:<br><b style="font-size:18px; color:#d9534f;">${appId}</b>?<br>
            <span style="color:#888; font-size:13px;">Zostanie usunięty plik oraz wpis z installed_apps.json.</span>
        </div>
        <div style="display:flex; justify-content:center; gap:20px;">
            <button id="btn-cancel" style="padding:12px 30px; border-radius:8px; border:1px solid #ccc; cursor:pointer; background:#f5f5f5; font-weight:bold;">ANULUJ</button>
            <button id="btn-real-delete" style="padding:12px 30px; border-radius:8px; border:none; cursor:pointer; background:#d9534f; color:white; font-weight:bold;">POTWIERDŹ</button>
        </div>
    `;

    overlay.appendChild(dialog);
    document.body.appendChild(overlay);

    document.getElementById('btn-cancel').onclick = () => document.body.removeChild(overlay);

    document.getElementById('btn-real-delete').onclick = async () => {
        document.body.removeChild(overlay);
        
        // Tworzymy FormData, aby PHP odebrało to w $_POST
        const formData = new FormData();
        formData.append('filename', appId);

        try {
            const response = await fetch('uninstall_engine.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if(result.status === 'success') {
                // Skoro silnik usunął wpis z JSONa, odświeżamy pulpit
                if(typeof window.refreshDesktop === 'function') {
                    window.refreshDesktop();
                }
                alert(result.msg);
            } else {
                alert("Błąd: " + result.msg);
            }
        } catch (e) {
            console.error("Błąd połączenia:", e);
            alert("Krytyczny błąd komunikacji z uninstall_engine.php!");
        }
    };
}

// --- FUNKCJA ŁADNEGO ALERU (ZAMIAST ALERT) ---
function pingosAlert(title, message, type = 'info') {
    const overlay = document.createElement('div');
    overlay.style = "position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:30000; display:flex; align-items:center; justify-content:center; backdrop-filter: blur(5px);";
    const color = type === 'error' ? '#d9534f' : '#5cb85c';

    const dialog = document.createElement('div');
    dialog.style = `background:#fff; padding:25px; border-radius:12px; box-shadow:0 15px 40px rgba(0,0,0,0.4); width:350px; text-align:center; border-top: 5px solid ${color}; font-family: sans-serif;`;
    dialog.innerHTML = `
        <div style="font-weight:bold; margin-bottom:10px; font-size:18px; color:#333;">${title}</div>
        <div style="margin-bottom:20px; color:#666; font-size:14px; line-height:1.4;">${message}</div>
        <button id="alert-ok" style="padding:10px 30px; border-radius:6px; border:none; cursor:pointer; background:${color}; color:white; font-weight:bold; width:100%;">OK</button>
    `;
    overlay.appendChild(dialog);
    document.body.appendChild(overlay);
    document.getElementById('alert-ok').onclick = () => document.body.removeChild(overlay);
}

// --- GŁÓWNA FUNKCJA ODINSTALOWANIA ---
function uninstallApp() {
    const menu = document.getElementById('app-context-menu');
    const appId = menu.dataset.targetApp;

    const overlay = document.createElement('div');
    overlay.style = "position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:20000; display:flex; align-items:center; justify-content:center; backdrop-filter: blur(8px);";

    const dialog = document.createElement('div');
    dialog.style = "background:#fff; padding:30px; border-radius:15px; box-shadow:0 20px 60px rgba(0,0,0,0.6); width:400px; text-align:center; border: 1px solid #ccc; font-family: sans-serif;";
    dialog.innerHTML = `
        <div style="font-weight:bold; margin-bottom:15px; font-size:22px; color:#222;">ODINSTALOWAĆ?</div>
        <div style="margin-bottom:25px; color:#444; line-height:1.6; font-size:15px;">Czy na pewno chcesz usunąć aplikację:<br><b style="font-size:18px; color:#d9534f;">${appId}</b>?</div>
        <div style="display:flex; justify-content:center; gap:20px;">
            <button id="btn-cancel-uninstall" style="padding:12px 30px; border-radius:8px; border:1px solid #ccc; cursor:pointer; background:#f5f5f5; font-weight:bold; flex:1;">ANULUJ</button>
            <button id="btn-confirm-uninstall" style="padding:12px 30px; border-radius:8px; border:none; cursor:pointer; background:#d9534f; color:white; font-weight:bold; flex:1;">USUŃ</button>
        </div>
    `;

    overlay.appendChild(dialog);
    document.body.appendChild(overlay);

    document.getElementById('btn-cancel-uninstall').onclick = () => document.body.removeChild(overlay);

    document.getElementById('btn-confirm-uninstall').onclick = async () => {
        document.body.removeChild(overlay);
        const formData = new FormData();
        formData.append('filename', appId);

        try {
            const response = await fetch('uninstall_engine.php', { method: 'POST', body: formData });
            const result = await response.json();
            if(result.status === 'success') {
                if(typeof window.refreshDesktop === 'function') window.refreshDesktop();
                pingosAlert("Sukces", result.msg, 'success');
            } else {
                pingosAlert("Błąd", result.msg, 'error');
            }
        } catch (e) {
            pingosAlert("Błąd", "Nie można połączyć z uninstall_engine.php", 'error');
        }
    };
}

// ==========================================
// SYSTEM STEROWANIA PADEM (PING OS)
// ==========================================
let gpIndex = null;
let cursorX = window.innerWidth / 2;
let cursorY = window.innerHeight / 2;
let lastButtons = {}; 

window.addEventListener("gamepadconnected", (e) => {
    gpIndex = e.gamepad.index;
    const cursorEl = document.getElementById('gamepad-cursor');
    if (cursorEl) cursorEl.style.display = 'block';
});

function handleGamepad() {
    const gamepads = navigator.getGamepads();
    const gp = gamepads[gpIndex];

    if (!gp || !gp.buttons) {
        requestAnimationFrame(handleGamepad);
        return;
    }

    const deadzone = 0.15;
    const maxSpeed = 10; 
    const moveSpeed = 15; 

    // --- 1. RUCH KURSOREM ---
    if (Math.abs(gp.axes[0]) > deadzone) cursorX += gp.axes[0] * maxSpeed;
    if (Math.abs(gp.axes[1]) > deadzone) cursorY += gp.axes[1] * maxSpeed;
    
    cursorX = Math.max(0, Math.min(window.innerWidth, cursorX));
    cursorY = Math.max(0, Math.min(window.innerHeight, cursorY));

    const cursorEl = document.getElementById('gamepad-cursor');
    if (cursorEl) {
        cursorEl.style.left = cursorX + 'px';
        cursorEl.style.top = cursorY + 'px';
    }

    // --- 2. PRZESUWANIE AKTYWNEGO OKNA ---
    const activeWin = document.querySelector('.window.active');
    if (activeWin && !activeWin.classList.contains('maximized')) {
        if (gp.axes[2] !== undefined && Math.abs(gp.axes[2]) > deadzone) {
            let currentLeft = parseFloat(activeWin.style.left) || 0;
            activeWin.style.left = (currentLeft + gp.axes[2] * moveSpeed) + 'px';
        }
        if (gp.axes[3] !== undefined && Math.abs(gp.axes[3]) > deadzone) {
            let currentTop = parseFloat(activeWin.style.top) || 0;
            activeWin.style.top = (currentTop + gp.axes[3] * moveSpeed) + 'px';
        }
    }

    // --- 3. CELOWANIE ---
    const target = document.elementFromPoint(cursorX, cursorY);
    const winUnderCursor = target?.closest('.window'); 
    
    // Kluczowa zmiana: szukamy .desktop-item, bo tam masz ondblclick w PHP
    const desktopItem = target?.closest('.desktop-item');

    const checkJustPressed = (idx) => {
        if (!gp.buttons[idx]) return false;
        const pressed = gp.buttons[idx].pressed;
        const wasDown = !!lastButtons[idx];
        return pressed && !wasDown;
    };

    // --- 4. AKCJE PRZYCISKÓW ---

    // PRZYCISK X (0) - Klikanie i otwieranie
    if (gp.buttons[0]) {
        if (gp.buttons[0].pressed && !lastButtons[0]) {
            const mouseDownEvt = new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window, clientX: cursorX, clientY: cursorY });
            target?.dispatchEvent(mouseDownEvt);
            
            if (winUnderCursor) {
                focusWindow(winUnderCursor.id); 
            }
        } else if (!gp.buttons[0].pressed && lastButtons[0]) {
            const mouseUpEvt = new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window, clientX: cursorX, clientY: cursorY });
            target?.dispatchEvent(mouseUpEvt);

            if (desktopItem) {
                // Jeśli to ikona pulpitu, wymuszamy dblclick na właściwym elemencie
                const dblClickEvt = new MouseEvent('dblclick', { bubbles: true, cancelable: true, view: window, clientX: cursorX, clientY: cursorY });
                desktopItem.dispatchEvent(dblClickEvt);
            } else {
                target?.click();
            }
        }
    }

    // KÓŁKO (1) - Zamykanie aktywnego okna
    if (checkJustPressed(1) && activeWin) closeApp(activeWin.id);

    // KWADRAT (2) - Maksymalizacja aktywnego okna
    if (checkJustPressed(2) && activeWin) toggleMaximize(activeWin.id);

    // TRÓJKĄT (3) - Minimalizacja aktywnego okna
    if (checkJustPressed(3) && activeWin) minimizeWindow(activeWin.id);

    // Zapis stanu przycisków
    for (let i = 0; i < gp.buttons.length; i++) {
        lastButtons[i] = gp.buttons[i].pressed;
    }

    requestAnimationFrame(handleGamepad);
}

handleGamepad();




// --- LOGIKA PRZECIĄGANIA I INTERAKCJI PINGWINKA ---
(function() {
    const penguin = document.getElementById('wallpaper-penguin');
    if (!penguin) return;
    let isDragging = false;
    let offsetX = 0;
    let offsetY = 0;
    let startX = 0;
    let startY = 0;
    let dragDistance = 0;
    let qnaData = [];

    penguin.addEventListener('mousedown', function(e) {
        if (e.target.closest('#penguin-bubble')) return;
        if (e.button !== 0) return;
        isDragging = true;
        const rect = penguin.getBoundingClientRect();
        offsetX = e.clientX - rect.left;
        offsetY = e.clientY - rect.top;
        startX = e.clientX;
        startY = e.clientY;
        dragDistance = 0;
        penguin.style.cursor = 'grabbing';
        e.preventDefault();
    });

    document.addEventListener('mousemove', function(e) {
        if (!isDragging) return;
        let dx = e.clientX - startX;
        let dy = e.clientY - startY;
        dragDistance = Math.sqrt(dx*dx + dy*dy);

        let x = e.clientX - offsetX;
        let y = e.clientY - offsetY;
        
        x = Math.max(0, Math.min(window.innerWidth - penguin.clientWidth, x));
        y = Math.max(0, Math.min(window.innerHeight - penguin.clientHeight - 48, y)); // 48px to wysokość paska zadań
        
        penguin.style.left = x + 'px';
        penguin.style.top = y + 'px';
    });

    document.addEventListener('mouseup', function(e) {
        if (isDragging) {
            isDragging = false;
            penguin.style.cursor = 'grab';
            if (dragDistance < 6) {
                togglePenguinBubble();
            }
        }
    });

    async function loadQna() {
        if (qnaData.length > 0) return qnaData;
        try {
            const res = await fetch('pingwin_qna.json?t=' + Date.now());
            qnaData = await res.json();
        } catch (err) {
            console.error('Błąd ładowania pytań pingwinka:', err);
            qnaData = [
                { "q": "Czym jesteś?", "a": "Jestem pomocnikiem PingOS!" }
            ];
        }
        return qnaData;
    }

    window.togglePenguinBubble = async function() {
        const bubble = document.getElementById('penguin-bubble');
        if (!bubble) return;
        if (bubble.style.display === 'block') {
            bubble.style.display = 'none';
        } else {
            bubble.style.display = 'block';
            showQuestions();
        }
    };

    async function showQuestions() {
        const content = document.getElementById('penguin-bubble-content');
        if (!content) return;
        content.innerHTML = '<div style="text-align:center;padding:10px;">Ładowanie pytań...</div>';
        
        const data = await loadQna();
        content.innerHTML = '';
        data.forEach((item, index) => {
            const qDiv = document.createElement('div');
            qDiv.className = 'penguin-question-item';
            qDiv.innerText = item.q;
            qDiv.onclick = (e) => {
                e.stopPropagation();
                showAnswer(item.a);
            };
            content.appendChild(qDiv);
        });
    }

    function showAnswer(answerText) {
        const content = document.getElementById('penguin-bubble-content');
        if (!content) return;
        content.innerHTML = `
            <div style="line-height:1.4; margin-bottom:8px;">${answerText}</div>
            <button class="penguin-back-btn" onclick="event.stopPropagation(); showQuestionsListGlobal()">Wróć do pytań</button>
        `;
    }

    window.showQuestionsListGlobal = showQuestions;
})();

</script>

</body>
</html>