<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nieuwegein ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <style>
        body { background-color: #f4f6f8; font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }
        
        /* Sidebar styling */
        .sidebar { position: fixed; top: 0; bottom: 0; left: 0; width: 260px; background-color: #011e41; z-index: 600; }
        .sidebar-header { background-color: #01162e; padding: 20px; color: white; font-weight: bold; text-align: center; border-bottom: 1px solid #0d325e;}
        .sidebar a { display: block; padding: 15px 25px; color: #b6cce2; text-decoration: none; border-left: 4px solid transparent; transition: 0.2s;}
        .sidebar a.active, .sidebar a:hover { background-color: #022c5e; color: white; border-left-color: #3b9dfc; }
        
        /* Main content */
        .main { margin-left: 260px; padding: 30px; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: white; padding: 15px; border-radius: 4px; border-top: 4px solid #0070d2; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        
        /* Cards */
        .erp-card { background: white; border: 1px solid #dce2e6; border-radius: 4px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); padding: 20px; margin-bottom: 20px; }
    </style>
</head>
<body>

<nav class="sidebar">
    <div class="sidebar-header">NIEUWEGEIN APP</div>
    <a href="/nieuwegein/schedule" class="{{ request()->is('nieuwegein/schedule') ? 'active' : '' }}">📅 Planning</a>
    <a href="/nieuwegein/team" class="{{ request()->is('nieuwegein/team') ? 'active' : '' }}">👥 Mijn Team</a>
    <a href="/nieuwegein/admin" class="{{ request()->is('nieuwegein/admin') ? 'active' : '' }}">⚙️ Admin & Setup</a>
</nav>

<main class="main">
    @yield('content')
</main>

</body>
</html>
