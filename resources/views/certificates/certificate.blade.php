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
    border: 1px solid #a45dbe;
  }

  /* Corner ornaments */
  .corner {
    position: absolute;
    width: 18mm;
    height: 18mm;
    border-color: #a45dbe;
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
    background: #a45dbe;
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
    border-bottom: 1px solid #a45dbe;
    padding-bottom: 2mm;
    margin-bottom: 6mm;
    min-width: 100mm;
  }

  /* النص العربي يصل من ArabicText مشكّلاً ومرتّباً بصرياً بالكامل، لذلك
     يجب أن يُرسم كما هو من اليسار لليمين. أي direction:rtl هنا سيجعل
     DomPDF يعكس ترتيب الكلمات مرة ثانية (كل كلمة تُرسم كمقطع مستقل)
     فيظهر ترتيب الكلمات مقلوباً. نكتفي برفع الحجم لأن الحروف العربية
     أقصر بصرياً من اللاتينية بنفس المقاس. */
  .rtl-text {
    direction: ltr;
    font-size: 1.15em;
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
    border: 1px solid #a45dbe;
    color: #a45dbe;
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

  /* Platform seal — pure CSS circles, DomPDF-safe */
  .seal {
    position: absolute;
    bottom: 16mm;
    right: 16mm;
    width: 42mm;
    height: 42mm;
  }
  .seal-ring-outer {
    position: absolute;
    top: 0; left: 0;
    width: 42mm; height: 42mm;
    border-radius: 50%;
    border: 2.5px solid #a45dbe;
  }
  .seal-ring-mid {
    position: absolute;
    top: 2mm; left: 2mm;
    width: 38mm; height: 38mm;
    border-radius: 50%;
    border: 1px solid #a45dbe;
  }
  .seal-ring-inner {
    position: absolute;
    top: 3.5mm; left: 3.5mm;
    width: 35mm; height: 35mm;
    border-radius: 50%;
    border: 2px solid #a45dbe;
  }
  .seal-body {
    position: absolute;
    top: 10mm; left: 0;
    width: 42mm;
    text-align: center;
    color: #a45dbe;
  }
  .seal-stars-top {
    font-size: 6pt;
    letter-spacing: 3px;
    margin-bottom: 1.5mm;
  }
  .seal-brand {
    font-size: 9.5pt;
    font-weight: bold;
    letter-spacing: 1px;
    margin-bottom: 1.5mm;
  }
  .seal-line {
    width: 20mm;
    height: 1px;
    background-color: #a45dbe;
    margin: 0 auto 1.5mm auto;
  }
  .seal-cert {
    font-size: 6pt;
    letter-spacing: 3px;
    text-transform: uppercase;
    margin-bottom: 1.5mm;
  }
  .seal-platform {
    font-size: 5pt;
    letter-spacing: 0.5px;
    margin-bottom: 1.5mm;
  }
  .seal-stars-bot {
    font-size: 6pt;
    letter-spacing: 3px;
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

  <div class="presented-to">This certificate is proudly presented to</div>

  <div class="student-name {{ $nameIsArabic ? 'rtl-text' : '' }}">{{ $studentName }}</div>

  <div class="body-text">
    for successfully completing the course<br>
    <span class="course-name {{ $titleIsArabic ? 'rtl-text' : '' }}">&ldquo;{{ $courseTitle }}&rdquo;</span>
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

<div class="seal">
  <div class="seal-ring-outer"></div>
  <div class="seal-ring-mid"></div>
  <div class="seal-ring-inner"></div>
  <div class="seal-body">
    <div class="seal-stars-top">&#9733; &#9733; &#9733;</div>
    <div class="seal-brand">EduQuest</div>
    <div class="seal-line"></div>
    <div class="seal-cert">CERTIFIED</div>
    <div class="seal-platform">Accredited Platform</div>
    <div class="seal-stars-bot">&#9670; &#9670; &#9670;</div>
  </div>
</div>

</body>
</html>
