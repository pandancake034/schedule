<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nieuwegein ERP</title>
    
    {{-- 1. Bootstrap CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    {{-- 2. Bootstrap Icons (Voor professionele iconen i.p.v. emojis) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    {{-- 3. FullCalendar JS --}}
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>

    {{-- 4. Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        :root {
            --erp-sidebar-bg: #1e293b;       /* Donkergrijs/blauw */
            --erp-sidebar-active: #0f172a;   /* Nog donkerder voor actief */
            --erp-primary: #0f6cbd;          /* Zakelijk blauw (zoals Word/Outlook) */
            --erp-bg: #f3f4f6;               /* Lichte achtergrond */
            --erp-border: #e2e8f0;           /* Subtiele lijnen */
        }

        body { 
            background-color: var(--erp-bg); 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            font-size: 0.9rem; /* Iets compacter voor ERP look */
            color: #334155;
            overflow-x: hidden; 
        }
        
        /* Sidebar styling */
        .sidebar { 
            position: fixed; top: 0; bottom: 0; left: 0; width: 240px; 
            background-color: var(--erp-sidebar-bg); 
            color: #94a3b8;
            z-index: 1000; 
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        }
        
        .sidebar-header { 
            background-color: var(--erp-sidebar-active); 
            padding: 18px 20px; 
            color: white; 
            font-weight: 600; 
            font-size: 1.1rem;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar a { 
            display: flex; 
            align-items: center;
            gap: 12px;
            padding: 12px 20px; 
            color: #cbd5e1; 
            text-decoration: none; 
            border-left: 3px solid transparent; 
            transition: all 0.2s ease-in-out;
        }

        .sidebar a:hover { 
            background-color: rgba(255,255,255,0.05); 
            color: white; 
        }

        .sidebar a.active { 
            background-color: var(--erp-sidebar-active); 
            color: white; 
            border-left-color: var(--erp-primary); 
        }
        
        /* Main content */
        .main { margin-left: 240px; padding: 25px; }
        
        /* Top Bar */
        .top-bar { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
            background: white; 
            padding: 12px 20px; 
            border: 1px solid var(--erp-border);
            border-radius: 2px;
        }
        .top-bar h5 {
            font-size: 1.1rem;
            color: #1e293b;
            margin: 0;
            font-weight: 600;
        }
        
        /* Cards & Panels */
        .erp-card { 
            background: white; 
            border: 1px solid var(--erp-border); 
            border-radius: 2px; /* Zakelijke, scherpe hoeken */
            padding: 20px; 
            margin-bottom: 20px; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }

        .btn {
            border-radius: 2px; /* Vierkantere knoppen */
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .table thead th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--erp-border);
        }
    </style>
</head>
<body>

<nav class="sidebar">
    <div class="sidebar-header">
        <i class="bi bi-grid-3x3-gap-fill"></i> Nieuwegein ERP
    </div>
    <a href="/nieuwegein/schedule" class="{{ request()->is('nieuwegein/schedule') ? 'active' : '' }}">
        <i class="bi bi-calendar3"></i> Planning
    </a>
    <a href="/nieuwegein/team" class="{{ request()->is('nieuwegein/team') ? 'active' : '' }}">
        <i class="bi bi-people-fill"></i> Medewerkers
    </a>
    <a href="/nieuwegein/admin" class="{{ request()->is('nieuwegein/admin') ? 'active' : '' }}">
        <i class="bi bi-sliders"></i> Configuratie
    </a>
</nav>

<main class="main">
    @yield('content')
</main>

</body>
</html>