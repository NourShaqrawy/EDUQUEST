<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: DejaVu Sans, sans-serif;
    background: #fff;
    width: 297mm;
    height: 210mm;
    position: relative;
    overflow: hidden;
  }

  /* Outer border frame */
  .frame-outer {
    position: absolute;
    inset: 8mm;
    border: 3px solid #1e3a5f;
  }
  .frame-inner {
    position: absolute;
    inset: 11mm;
    border: 1px solid #c9a84c;
  }

  /* Corner ornaments */
  .corner {
    position: absolute;
    width: 18mm;
    height: 18mm;
    border-color: #c9a84c;
    border-style: solid;
  }
  .corner-tl { top: 6mm;  left: 6mm;  border-width: 3px 0 0 3px; }
  .corner-tr { top: 6mm;  right: 6mm; border-width: 3px 3px 0 0; }
  .corner-bl { bottom: 6mm; left: 6mm;  border-width: 0 0 3px 3px; }
  .corner-br { bottom: 6mm; right: 6mm; border-width: 0 3px 3px 0; }

  /* Content wrapper */
  .content {
    position: absolute;
    inset: 14mm;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
  }

  .logo-line {
    font-size: 11pt;
    letter-spacing: 4px;
    color: #1e3a5f;
    text-transform: uppercase;
    margin-bottom: 4mm;
  }

  .title {
    font-size: 28pt;
    color: #1e3a5f;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 2mm;
  }

  .subtitle {
    font-size: 10pt;
    color: #666;
    letter-spacing: 3px;
    text-transform: uppercase;
    margin-bottom: 7mm;
  }

  .divider {
    width: 60mm;
    height: 1px;
    background: linear-gradient(to right, transparent, #c9a84c, transparent);
    margin-bottom: 7mm;
  }

  .presented-to {
    font-size: 9pt;
    color: #888;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 3mm;
  }

  .student-name {
    font-size: 22pt;
    color: #1e3a5f;
    border-bottom: 1px solid #c9a84c;
    padding-bottom: 2mm;
    margin-bottom: 6mm;
    min-width: 100mm;
  }

  .body-text {
    font-size: 10pt;
    color: #444;
    line-height: 1.7;
    max-width: 200mm;
    margin-bottom: 6mm;
  }

  .course-name {
    font-style: italic;
    color: #1e3a5f;
    font-size: 12pt;
  }

  .level-badge {
    display: inline-block;
    padding: 2mm 8mm;
    border: 1px solid #c9a84c;
    color: #c9a84c;
    font-size: 9pt;
    letter-spacing: 3px;
    text-transform: uppercase;
    margin-bottom: 8mm;
  }

  .footer {
    display: flex;
    justify-content: space-between;
    width: 100%;
    margin-top: 4mm;
  }

  .footer-item {
    text-align: center;
    width: 60mm;
  }

  .footer-label {
    font-size: 7pt;
    color: #aaa;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 1mm;
  }

  .footer-value {
    font-size: 8.5pt;
    color: #555;
    border-top: 1px solid #ddd;
    padding-top: 1mm;
  }
</style>
</head>
<body>

<div class="frame-outer"></div>
<div class="frame-inner"></div>
<div class="corner corner-tl"></div>
<div class="corner corner-tr"></div>
<div class="corner corner-bl"></div>
<div class="corner corner-br"></div>

<div class="content">
  <div class="logo-line">EduQuest &mdash; E-Learning Platform</div>

  <div class="title">Certificate</div>
  <div class="subtitle">of completion</div>

  <div class="divider"></div>

  <div class="presented-to">This certificate is proudly presented to</div>

  <div class="student-name">{{ $certificate->user->name }}</div>

  <div class="body-text">
    for successfully completing the course<br>
    <span class="course-name">&ldquo;{{ $certificate->course->title }}&rdquo;</span>
  </div>

  <div class="level-badge">
    @switch($certificate->level)
      @case('excellent') With Excellent Distinction @break
      @case('good')      With Good Standing        @break
      @default           With Satisfactory Completion
    @endswitch
  </div>

  <div class="footer">
    <div class="footer-item">
      <div class="footer-label">Issue Date</div>
      <div class="footer-value">{{ $certificate->issued_at->format('F j, Y') }}</div>
    </div>
    <div class="footer-item">
      <div class="footer-label">Certificate ID</div>
      <div class="footer-value">{{ $certificate->certificate_code }}</div>
    </div>
    <div class="footer-item">
      <div class="footer-label">Authorized by</div>
      <div class="footer-value">EduQuest Platform</div>
    </div>
  </div>
</div>

</body>
</html>
