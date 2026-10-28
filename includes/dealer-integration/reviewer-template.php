<?php
/** Private sign-in and one-time administrator credential receipt. */
defined('ABSPATH') || exit;
use AutoAgora\Dealer\Reviewer;
use AutoAgora\Dealer\Workspace;
$assets = get_stylesheet_directory_uri() . '/includes/dealer-integration/assets/';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title><?php echo $created ? 'Reviewer access created' : 'Private review sign-in'; ?> · AutoAgora</title><link rel="stylesheet" href="<?php echo esc_url($assets . 'tabler/tabler-1.7.1.min.css?v=1.7.1'); ?>"><link rel="stylesheet" href="<?php echo esc_url($assets . 'workspace.css?v=' . filemtime(__DIR__ . '/assets/workspace.css')); ?>"><script src="<?php echo esc_url($assets . 'workspace.js?v=' . filemtime(__DIR__ . '/assets/workspace.js')); ?>" defer></script><style>@font-face{font-family:Inter;font-display:swap;src:url(<?php echo esc_url(get_template_directory_uri() . '/assets/fonts/builder/Inter.woff2'); ?>) format('woff2')}</style></head>
<body class="agw agw-signin"><main class="agw-login-shell"><img class="agw-login-logo" src="<?php echo esc_url($assets . 'autoagora-logo.png'); ?>" width="160" alt="AutoAgora"><section class="card card-body agw-login-panel">
<span class="agw-eyebrow">PRIVATE INTEGRATION REVIEW</span>
<h1><?php echo $created ? 'Reviewer access created' : 'Sign in to the workspace'; ?></h1>
<?php if ($created) : ?>
<p>Share these details privately with <?php echo esc_html(ucfirst($created['username'])); ?>. The password is shown only in this response and cannot be retrieved later.</p>
<label class="form-label agw-field">Sign-in link<input class="form-control" type="url" readonly value="<?php echo esc_url(Reviewer::url()); ?>" autocomplete="off"></label>
<label class="form-label agw-field">Username<input class="form-control" type="text" readonly value="<?php echo esc_attr($created['username']); ?>" autocomplete="off"></label>
<label class="form-label agw-field">Generated password<input class="form-control" id="reviewer-created-password" type="password" readonly value="<?php echo esc_attr($created['password']); ?>" autocomplete="new-password"></label>
<button type="button" class="btn btn-primary agw-password-toggle" data-toggle-password="reviewer-created-password">Show password</button>
<div class="alert alert-secondary agw-dialog-note"><?php echo Workspace::icon('shield-check'); ?><span>Expires <?php echo esc_html(wp_date('j F Y', $created['expires'])); ?>. Review workspace access only. No administrator permissions or email account.</span></div>
<a class="btn btn-primary agw-login-submit" href="<?php echo esc_url(Workspace::url(['tab' => 'access'])); ?>">Manage reviewer access</a>
<?php else : ?>
<p>Use the username and password David provided. This account opens the private integration review only.</p>
<?php if ($error) : ?><div class="alert alert-danger agw-notice" role="alert"><?php echo esc_html($error); ?></div><?php endif; ?>
<form method="post" data-review-login action="<?php echo esc_url(Reviewer::url()); ?>"><input type="hidden" name="review_csrf" value="<?php echo esc_attr($csrf); ?>">
<label class="form-label agw-field">Username<input class="form-control" name="review_username" type="text" required maxlength="64" autocomplete="username" autocapitalize="none" spellcheck="false"></label>
<label class="form-label agw-field">Password<input class="form-control" id="reviewer-login-password" name="review_password" type="password" required maxlength="4096" autocomplete="current-password"></label>
<button class="btn btn-primary agw-login-submit">Sign in</button></form>
<div class="alert alert-secondary agw-dialog-note"><?php echo Workspace::icon('shield-check'); ?><span>Access is limited and expires automatically. Contact David if your sign-in details need replacing.</span></div>
<?php endif; ?></section><p class="agw-login-footer">AutoAgora · Private dealer integration workspace</p></main></body></html>
