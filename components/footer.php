    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- FullCalendar JS (if needed) -->
    <?php if (isset($useFullCalendar) && $useFullCalendar): ?>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.js'></script>
    <?php endif; ?>
    
    <!-- Custom JS (if needed) -->
    <?php if (isset($customJS) && $customJS): ?>
    <script src="<?php echo $customJS; ?>"></script>
    <?php endif; ?>
</body>
</html>
