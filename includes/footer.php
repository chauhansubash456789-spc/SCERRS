</main>

<footer class="scerrs-footer">
  <div class="container">
    <div class="footer-container">
      <!-- Branding & Info -->
      <div class="footer-col branding">
        <h3 class="footer-logo">SCERRS Nepal</h3>
        <p>Smart Citizen Emergency Reporting &amp; Response System. Connecting citizens with emergency responders in real-time.</p>
        <span class="emergency-badge">Emergency Dial: 100 / 101 / 102</span>
      </div>

      <!-- Incident Categories -->
      <div class="footer-col">
        <h4>Report Categories</h4>
        <ul>
          <li><a href="<?= url('citizen/report_new.php') ?>">Accidents &amp; Fire</a></li>
          <li><a href="<?= url('citizen/report_new.php') ?>">Floods &amp; Landslides</a></li>
          <li><a href="<?= url('citizen/report_new.php') ?>">Violence &amp; Crime</a></li>
          <li><a href="<?= url('citizen/report_new.php') ?>">Missing Persons</a></li>
        </ul>
      </div>

      <!-- Key Features -->
      <div class="footer-col">
        <h4>System Features</h4>
        <ul>
          <li><a href="<?= url('index.php') ?>#how-it-works">GPS Auto-Location</a></li>
          <li><a href="<?= url('index.php') ?>#how-it-works">Authority Dashboard</a></li>
          <li><a href="<?= url('index.php') ?>#how-it-works">Live Progress Tracker</a></li>
          <li><a href="<?= url('index.php') ?>#how-it-works">Offline Mode</a></li>
        </ul>
      </div>

      <!-- Authorities & Project Info -->
      <div class="footer-col">
        <h4>Project Details</h4>
        <p><strong>Developed by:</strong><br>Subash Prasad Chauhan &amp; Nirjal Basnet</p>
        <p class="academic-tag">4th Semester Project Proposal</p>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> SCERRS Nepal. Building a Safer Nepal, One Report at a Time.</p>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/theme.js') ?>"></script>
<?php if ($load_map): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>
<?php if ($load_charts): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?php endif; ?>
<?= $extra_scripts ?? '' ?>
</body>
</html>
