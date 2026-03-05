<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Require login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once "header.php";
?>

<div class="card">
  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">ℹ️ About PeachTrack</h2>
      <div class="muted">Shift, tips, and reporting dashboard</div>
    </div>
  </div>

  <div style="height:14px"></div>

  <div class="grid" style="gap:12px;">
    <div class="card" style="margin:0;">
      <h3 style="margin-top:0;">Team</h3>
      <p class="muted" style="margin:0;">
        Team name: <strong>Cyber Dominion</strong><br />
        Program: <strong>Computer Information Technology</strong><br />
        Institution: <strong>Lethbridge College</strong><br />
        Year: <strong>2026</strong>
      </p>
    </div>

    <div class="card" style="margin:0;">
      <h3 style="margin-top:0;">Purpose</h3>
      <p class="muted" style="margin:0;">
        PeachTrack is a role-based web app for managing shifts, tips, and payroll across your business.
        Employees can start/end shifts and log tips, while managers monitor active shifts and generate reports.
      </p>
    </div>

    <div class="card" style="margin:0;">
      <h3 style="margin-top:0;">Tech</h3>
      <ul class="muted" style="margin:0; padding-left:18px;">
        <li>PHP + MySQL</li>
        <li>MAMP (local development)</li>
        <li>Chart.js (reports)</li>
      </ul>
    </div>
  </div>
</div>

<?php require_once "footer.php"; ?>
