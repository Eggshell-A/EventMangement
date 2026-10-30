<?php
if(!isset($view,$escape)){http_response_code(404);exit;}
?>
<!doctype html>
<html lang="en">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="color-scheme" content="dark">
 <meta name="robots" content="noindex, nofollow">
 <title>Let's refresh your form · Fairboard</title>
 <link rel="icon" href="<?=$escape($view['asset_base'])?>favicon.svg">
 <link rel="stylesheet" href="<?=$escape($view['asset_base'])?>form-recovery.css">
</head>
<body class="recovery-page">
 <a class="recovery-skip" href="#main">Skip to recovery steps</a>
 <header class="recovery-header">
  <a class="recovery-brand" href="<?=$escape($view['home_url'])?>" aria-label="Fairboard home"><span class="recovery-brand-icon" aria-hidden="true">f</span>fairboard<span class="recovery-brand-dot">.</span></a>
  <span class="recovery-header-note"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 3 20 6v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-6"/></svg>Looking out for your account</span>
 </header>
 <main id="main" class="recovery-main">
  <section class="recovery-card" aria-labelledby="recovery-title">
   <div class="recovery-hero">
    <div class="recovery-copy">
     <p class="recovery-eyebrow"><span aria-hidden="true"></span>SUBMISSION PAUSED</p>
     <h1 id="recovery-title">Your form needs<br><em>a fresh start.</em></h1>
     <p class="recovery-lead">We couldn't match this form to your current session, so we stopped this submission to help protect your account.</p>
     <p class="recovery-safe"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>No changes were made by this request.</p>
     <div class="recovery-actions">
      <a class="recovery-primary" data-recovery-link href="<?=$escape($view['url'])?>"><?=$escape($view['label'])?><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>
      <?php if($view['url']!==$view['home_url']):?><a class="recovery-secondary" href="<?=$escape($view['home_url'])?>">Back to home</a><?php endif;?>
     </div>
     <p class="recovery-hint">Opens a fresh page. Your previous submission won't be resent.</p>
    </div>
    <div class="recovery-visual" aria-hidden="true"><img src="<?=$escape($view['asset_base'])?>art/form-recovery.svg" alt="" width="360" height="330"><span>YOUR NEXT STEP, PROTECTED.</span></div>
   </div>
   <div class="recovery-guidance">
    <section class="recovery-next" aria-labelledby="next-title">
     <p class="recovery-section-label">LET'S GET YOU BACK</p><h2 id="next-title">What to do next</h2>
     <ol>
      <li><span class="recovery-number" aria-hidden="true">01</span><div><h3>Open the fresh page</h3><p>Use the button above instead of refreshing or resending this page.</p></div></li>
      <li><span class="recovery-number" aria-hidden="true">02</span><div><h3>Enter your details again</h3><p>For your privacy, we haven't copied your previous form entries.</p></div></li>
      <li><span class="recovery-number" aria-hidden="true">03</span><div><h3>Review, then submit once</h3><p>If you're asked to sign in again, complete sign-in before continuing.</p></div></li>
     </ol>
    </section>
    <section class="recovery-reasons" aria-labelledby="reasons-title">
     <p class="recovery-section-label">A QUICK EXPLANATION</p><h2 id="reasons-title">Why this can happen</h2>
     <p>This doesn't necessarily mean you did anything wrong. Common reasons include:</p>
     <ul><li>The page was open for a while and the session ended.</li><li>You signed in or out in another tab.</li><li>Cookies were blocked, or the website address changed.</li></ul>
     <p class="recovery-reassurance">Starting with a new page gives your next submission a fresh security check.</p>
    </section>
   </div>
  </section>
  <details class="recovery-help">
   <summary><span>Still seeing this page?</span><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></summary>
   <div><p>Allow cookies for this site, then open it using its usual website address. Switching between an IP address and a public link, or between HTTP and HTTPS, can interrupt your session.</p><p>If it keeps happening, contact the site administrator with the time it happened and the page you were using. <strong>Do not share passwords, authenticator codes or recovery codes.</strong></p></div>
  </details>
  <footer class="recovery-footer"><span>Good plans are still ahead.</span><span>FORM CHECK <span aria-hidden="true">/</span> 403</span></footer>
 </main>
</body>
</html>
