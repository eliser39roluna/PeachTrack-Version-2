<?php
require 'db_config.php';

// Auto-create the contact_inquiry table if it doesn't exist yet
$conn->query("CREATE TABLE IF NOT EXISTS contact_inquiry (
    Inquiry_ID   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    Company      VARCHAR(200)  NOT NULL,
    Contact_Name VARCHAR(200)  NOT NULL,
    Email        VARCHAR(254)  NOT NULL,
    Phone        VARCHAR(50)       NULL,
    Employees    VARCHAR(20)       NULL,
    Message      TEXT          NOT NULL,
    Is_Read      TINYINT(1)    NOT NULL DEFAULT 0,
    Submitted_At DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (Inquiry_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$sent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company   = trim($_POST['company']   ?? '');
    $contact   = trim($_POST['contact']   ?? '');
    $email     = trim($_POST['email']     ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $employees = trim($_POST['employees'] ?? '');
    $message   = trim($_POST['message']   ?? '');

    if (!$company)  $errors[] = 'Company name is required.';
    if (!$contact)  $errors[] = 'Contact name is required.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (!$message)  $errors[] = 'Please include a message.';

    if (empty($errors)) {
        // 1. Save inquiry to the database (reliable, works on all environments)
        $stmt = $conn->prepare(
            "INSERT INTO contact_inquiry (Company, Contact_Name, Email, Phone, Employees, Message)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('ssssss', $company, $contact, $email, $phone, $employees, $message);
        $dbSaved = $stmt->execute();
        $stmt->close();

        // 2. Also attempt to send email (works on production servers with mail configured)
        $to      = 'resileanu@gmail.com';
        $subject = "PeachTrack Demo Request – {$company}";
        $body    = "New demo / business inquiry via PeachTrack login page.\n\n"
                 . "Company:    {$company}\n"
                 . "Contact:    {$contact}\n"
                 . "Email:      {$email}\n"
                 . "Phone:      " . ($phone ?: 'N/A') . "\n"
                 . "Employees:  " . ($employees ?: 'N/A') . "\n\n"
                 . "Message:\n{$message}\n";
        $headers = "From: noreply@peachtrack.com\r\nReply-To: {$email}";
        @mail($to, $subject, $body, $headers);

        if ($dbSaved) {
            $sent = true;
        } else {
            $errors[] = 'Sorry, we could not save your inquiry. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Contact Sales | PeachTrack</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    :root{
      --primary:#ff6b4a;
      --primary2:#ff8a72;
      --text:#111827;
      --muted:#6b7280;
      --border:rgba(17,24,39,.12);
      --shadow:0 18px 50px rgba(17,24,39,.22);
    }
    *{box-sizing:border-box;}

    body{
      margin:0;
      min-height:100vh;
      display:grid;
      place-items:center;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      color:#fff;
      background:
        radial-gradient(1000px 700px at 15% 10%, rgba(255,107,74,.18), transparent 58%),
        radial-gradient(900px 600px at 90% 0%,  rgba(255,138,114,.14), transparent 60%),
        linear-gradient(135deg, rgba(17,24,39,.28), rgba(17,24,39,.10)),
        url('assets/img/login-bg2.jpg');
      background-repeat:no-repeat;
      background-size:cover;
      background-position:center;
      padding:28px;
    }

    .wrap{
      width:100%;
      max-width:980px;
      display:grid;
      grid-template-columns:1fr 1.3fr;
      gap:16px;
      align-items:stretch;
    }

    .panel{
      border-radius:24px;
      overflow:hidden;
      position:relative;
      box-shadow:var(--shadow);
      border:1px solid rgba(255,255,255,.22);
    }

    /* ── Left info panel ─── */
    .info-panel{
      background:rgba(15,8,3,.72);
      backdrop-filter:blur(18px);
      -webkit-backdrop-filter:blur(18px);
      border:1px solid rgba(255,138,114,.18);
      padding:40px 32px;
      display:flex;
      flex-direction:column;
      gap:28px;
    }

    .brand-logo{text-align:center;margin-bottom:4px;}
    .logo-img{
      width:210px;height:auto;
      display:block;margin:0 auto;
      mix-blend-mode:screen;
      -webkit-mix-blend-mode:screen;
    }
    .logo-sub{
      margin:6px 0 0;
      font-size:11px;font-weight:600;letter-spacing:.1em;
      text-transform:uppercase;color:rgba(255,138,114,.9);
      text-align:center;
    }

    .info-heading h2{
      margin:0 0 10px;font-size:26px;font-weight:900;line-height:1.25;
      letter-spacing:-.4px;
    }
    .info-heading h2 span{
      background:linear-gradient(90deg,var(--primary2),#ffd580);
      -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
    }
    .info-heading p{margin:0;font-size:13.5px;line-height:1.65;color:rgba(255,255,255,.78);}

    .contact-methods{display:flex;flex-direction:column;gap:12px;}
    .contact-method{
      display:flex;align-items:center;gap:13px;
      background:rgba(255,255,255,.06);
      border:1px solid rgba(255,138,114,.18);
      border-radius:14px;
      padding:13px 15px;
    }
    .cm-icon{
      width:36px;height:36px;border-radius:10px;flex-shrink:0;
      background:linear-gradient(135deg,var(--primary),var(--primary2));
      display:grid;place-items:center;font-size:17px;
      box-shadow:0 6px 18px rgba(255,107,74,.35);
    }
    .cm-text strong{display:block;font-size:12.5px;font-weight:800;color:#fff;margin-bottom:2px;}
    .cm-text span{font-size:12px;color:rgba(255,255,255,.65);}
    .cm-text a{color:var(--primary2);text-decoration:none;}
    .cm-text a:hover{text-decoration:underline;}

    .info-footer{
      margin-top:auto;
      font-size:11px;color:rgba(255,255,255,.40);
      line-height:1.6;
    }

    /* ── Right form panel ─── */
    .form-panel{
      background:rgba(15,8,3,.72);
      backdrop-filter:blur(18px);
      -webkit-backdrop-filter:blur(18px);
      border:1px solid rgba(255,138,114,.18);
      padding:36px 32px;
      display:flex;
      flex-direction:column;
    }

    .form-header{margin-bottom:24px;}
    .form-header h3{margin:0 0 6px;font-size:22px;font-weight:900;color:#fff;}
    .form-header p{margin:0;font-size:13px;color:rgba(255,255,255,.60);line-height:1.5;}

    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

    .form-group{margin-bottom:14px;}
    label{
      display:block;font-weight:700;font-size:11px;
      letter-spacing:.06em;text-transform:uppercase;
      margin-bottom:7px;color:rgba(255,255,255,.80);
    }
    input[type=text], input[type=email], input[type=tel], select, textarea{
      width:100%;
      padding:11px 14px;
      border-radius:12px;
      border:1.5px solid rgba(255,138,114,.22);
      background:rgba(255,255,255,.94);
      color:#111827;
      font-size:14px;
      font-family:inherit;
      outline:none;
      transition:border-color .2s, box-shadow .2s;
    }
    select{cursor:pointer;}
    textarea{resize:vertical;min-height:90px;}
    input:focus, select:focus, textarea:focus{
      border-color:var(--primary);
      box-shadow:0 0 0 4px rgba(255,107,74,.20);
      background:#fff;
    }

    .btn-submit{
      width:100%;
      margin-top:6px;
      padding:14px;
      border-radius:12px;
      border:0;cursor:pointer;
      font-weight:800;font-size:15px;letter-spacing:.02em;
      color:#fff;
      background:linear-gradient(135deg,var(--primary),var(--primary2));
      box-shadow:0 12px 32px rgba(255,107,74,.40);
      transition:opacity .2s, box-shadow .2s, transform .15s;
    }
    .btn-submit:hover{opacity:.93;box-shadow:0 16px 40px rgba(255,107,74,.48);transform:translateY(-1px);}
    .btn-submit:active{transform:translateY(0);}

    .back-link{
      display:inline-flex;align-items:center;gap:6px;
      margin-top:18px;
      font-size:13px;color:rgba(255,255,255,.55);
      text-decoration:none;
      transition:color .2s;
    }
    .back-link:hover{color:var(--primary2);}

    .alert{padding:12px 15px;border-radius:12px;margin-bottom:18px;font-size:13px;font-weight:600;}
    .alert.error{background:rgba(239,68,68,.18);border:1px solid rgba(239,68,68,.35);color:#fca5a5;}
    .alert.success{background:rgba(16,185,129,.18);border:1px solid rgba(16,185,129,.35);color:#6ee7b7;}

    @media(max-width:860px){
      .wrap{grid-template-columns:1fr;}
      .info-panel{display:none;}
    }
    @media(max-width:500px){
      .form-panel{padding:24px 18px;}
      .form-row{grid-template-columns:1fr;}
    }
  </style>
</head>
<body>

<div class="wrap">

  <!-- Left: info panel -->
  <aside class="panel info-panel" aria-label="Contact Info">

    <div class="brand-logo">
      <img src="assets/img/peachtrack-logo.png" class="logo-img" alt="PeachTrack"/>
      <p class="logo-sub">Workforce &amp; Payroll Management</p>
    </div>

    <div class="info-heading">
      <h2>Let&rsquo;s grow your<br/><span>business together.</span></h2>
      <p>Tell us about your team and we&rsquo;ll show you exactly how PeachTrack can streamline your operations from day one.</p>
    </div>

    <div class="contact-methods">
      <div class="contact-method">
        <div class="cm-icon">&#x1F4E7;</div>
        <div class="cm-text">
          <strong>Email Us</strong>
          <span><a href="mailto:resileanu@gmail.com">resileanu@gmail.com</a></span>
        </div>
      </div>
      <div class="contact-method">
        <div class="cm-icon">&#x1F4DE;</div>
        <div class="cm-text">
          <strong>Call Us</strong>
          <span><a href="tel:+13683998387">+1 (368) 399-8387</a></span>
        </div>
      </div>
      <div class="contact-method">
        <div class="cm-icon">&#x23F1;</div>
        <div class="cm-text">
          <strong>Response Time</strong>
          <span>We reply within 1 business day</span>
        </div>
      </div>
    </div>

    <div class="info-footer">
      &copy; <?php echo date('Y'); ?> PeachTrack &mdash; Workforce &amp; Payroll Management.<br/>
      Built for service businesses of every size.
    </div>

  </aside>

  <!-- Right: inquiry form -->
  <section class="panel form-panel" aria-label="Demo Request Form">

    <div class="form-header">
      <h3>Request a Demo</h3>
      <p>Fill in the form below and our team will be in touch to schedule your personalised walkthrough.</p>
    </div>

    <?php if ($sent): ?>
      <div class="alert success">
        &#x2713; Thank you, <strong><?php echo htmlspecialchars($_POST['contact']); ?></strong>! We&rsquo;ve received your inquiry and will be in touch within 1 business day.
      </div>
      <a href="login.php" class="back-link">&#x2190; Back to Sign In</a>

    <?php else: ?>

      <?php if (!empty($errors)): ?>
        <div class="alert error">
          <?php foreach($errors as $e): ?>
            <div>&#x26A0; <?php echo htmlspecialchars($e); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form action="contact.php" method="POST" novalidate>

        <div class="form-row">
          <div class="form-group">
            <label for="company">Company Name *</label>
            <input type="text" id="company" name="company" required
                   placeholder="Acme Hospitality Inc."
                   value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>"/>
          </div>
          <div class="form-group">
            <label for="contact">Your Name *</label>
            <input type="text" id="contact" name="contact" required
                   placeholder="Jane Smith"
                   value="<?php echo htmlspecialchars($_POST['contact'] ?? ''); ?>"/>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="email">Work Email *</label>
            <input type="email" id="email" name="email" required
                   placeholder="jane@yourcompany.com"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"/>
          </div>
          <div class="form-group">
            <label for="phone">Phone <span style="opacity:.55;font-weight:500;">(optional)</span></label>
            <input type="tel" id="phone" name="phone"
                   placeholder="+1 (604) 555-0100"
                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"/>
          </div>
        </div>

        <div class="form-group">
          <label for="employees">Number of Employees</label>
          <select id="employees" name="employees">
            <option value="" <?php echo empty($_POST['employees']) ? 'selected' : ''; ?>>Select a range&hellip;</option>
            <option value="1-10"    <?php echo ($_POST['employees'] ?? '') === '1-10'    ? 'selected' : ''; ?>>1 – 10</option>
            <option value="11-25"   <?php echo ($_POST['employees'] ?? '') === '11-25'   ? 'selected' : ''; ?>>11 – 25</option>
            <option value="26-50"   <?php echo ($_POST['employees'] ?? '') === '26-50'   ? 'selected' : ''; ?>>26 – 50</option>
            <option value="51-100"  <?php echo ($_POST['employees'] ?? '') === '51-100'  ? 'selected' : ''; ?>>51 – 100</option>
            <option value="100+"    <?php echo ($_POST['employees'] ?? '') === '100+'    ? 'selected' : ''; ?>>100+</option>
          </select>
        </div>

        <div class="form-group">
          <label for="message">Message *</label>
          <textarea id="message" name="message" required
                    placeholder="Tell us about your business and what you&rsquo;re looking for&hellip;"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="btn-submit">Send Inquiry &rarr;</button>

      </form>

      <a href="login.php" class="back-link">&#x2190; Back to Sign In</a>

    <?php endif; ?>

  </section>

</div>

</body>
</html>
