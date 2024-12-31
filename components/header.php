<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FullCalendar CSS (if needed) -->
    <?php if (isset($useFullCalendar) && $useFullCalendar): ?>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.css' rel='stylesheet' />
    <?php endif; ?>
    
    <!-- Custom CSS -->
    <link href="assets/css/custom.css" rel="stylesheet">
    
    <!-- Custom CSS for tooltips and calendar -->
    <style>
        .custom-tooltip-inner {
            max-width: 250px;
            text-align: left;
            background-color: rgba(0,0,0,0.9);
            padding: 10px;
            border-radius: 4px;
        }
        .custom-tooltip .tooltip-inner {
            background-color: rgba(0,0,0,0.9);
        }
        .fc-event {
            border: none !important;
        }
    </style>
    
    <!-- Custom CSS for sticky navigation and page layout -->
    <style>
        body {
            padding-top: 76px; /* Adjust based on navbar height */
        }
        .navbar.sticky-top {
            z-index: 1020;
            position: fixed;
            top: 0;
            width: 100%;
            box-shadow: 0 2px 4px rgba(255,255,255,0.1);
        }
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255,255,255,0.8);
        }
        .navbar-dark .navbar-nav .nav-link.active {
            color: #ffffff;
            font-weight: bold;
        }
        @media (max-width: 991.98px) {
            body {
                padding-top: 56px; /* Adjust for mobile navbar height */
            }
        }
        .navbar-collapse {
            max-height: calc(100vh - 76px);
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
