<?php
/** Connection-only login and verified signup. No public account directory. */
defined('ABSPATH') || exit;
use AutoAgora\Dealer\Workspace;
use AutoAgora\Dealer\Signup;
$assets = get_stylesheet_directory_uri() . '/includes/dealer-integration/assets/';
$titles = ['signin'=>'Sign in to AutoAgora','signup_phone'=>'Create your account','signup_verify'=>'Verify your phone','signup_details'=>'Complete your account'];
$signup = $session['signup'] ?? [];
$value = static fn ($key) => isset($_POST[$key]) && is_string($_POST[$key]) ? mb_substr(sanitize_text_field(wp_unslash($_POST[$key])),0,120) : '';
$signupType = $value('signup_type') ?: ($signup['type'] ?? $accountType);
$form = static function (string $operation) use ($csrf): void {
    echo '<input type="hidden" name="connect_csrf" value="' . esc_attr($csrf) . '"><input type="hidden" name="connect_action" value="' . esc_attr($operation) . '">';
};
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title><?php echo esc_html($titles[$stage]); ?> · AutoAgora</title>
<link rel="stylesheet" href="<?php echo esc_url($assets . 'tabler/tabler-1.7.1.min.css?v=1.7.1'); ?>">
<link rel="stylesheet" href="<?php echo esc_url($assets . 'workspace.css?v=' . filemtime(__DIR__ . '/assets/workspace.css')); ?>">
<link rel="stylesheet" href="<?php echo esc_url($assets . 'button-kit/buttons.css?v=' . filemtime(__DIR__ . '/assets/button-kit/buttons.css')); ?>">
<link rel="stylesheet" href="<?php echo esc_url($assets . 'connect.css?v=' . filemtime(__DIR__ . '/assets/connect.css')); ?>">
<style>@font-face{font-family:Inter;font-style:normal;font-weight:100 900;font-display:swap;src:url(<?php echo esc_url(get_template_directory_uri() . '/assets/fonts/builder/Inter.woff2'); ?>) format('woff2')}</style>
<?php if (Signup::ready() && !Signup::testing() && $stage === 'signup_phone') : ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
</head><body class="agw agw-signin ag-buttons ag-connect"><main class="agw-login-shell">
<img class="agw-login-logo" src="<?php echo esc_url($assets . 'autoagora-logo.png'); ?>" width="160" height="34" alt="AutoAgora">
<section class="card card-body agw-login-panel">
<?php if (wp_get_environment_type() !== 'production') : ?><span class="badge bg-azure-lt ag-connect-environment"><?php echo wp_get_environment_type() === 'local' ? 'Local test' : 'Staging test'; ?></span><?php endif; ?>
<h1><?php echo esc_html($titles[$stage]); ?></h1>
<?php if ($clientName) : ?><p class="text-secondary">Connect with <?php echo esc_html($clientName); ?>.</p><?php endif; ?>
<?php if ($error) : ?><div class="alert alert-danger" role="alert"><?php echo esc_html($error); ?></div><?php endif; ?>
<?php if ($stage === 'signin') : ?>
<form method="post"><?php $form('signin'); ?>
<label class="form-label agw-field">Phone or username<input class="form-control" name="dealer_login" type="text" maxlength="191" required autocomplete="username" value="<?php echo esc_attr($value('dealer_login')); ?>"></label>
<label class="form-label agw-field">Password<input class="form-control" name="dealer_password" type="password" maxlength="4096" required autocomplete="current-password"></label>
<button class="btn btn-primary agw-login-submit"><?php echo Workspace::icon('arrow-right'); ?>Sign in</button></form>
<?php if ($authorization && Signup::ready()) : ?><form method="post" class="ag-connect-secondary"><?php $form('signup'); ?><button class="btn btn-link">Create an AutoAgora account</button></form><?php endif; ?>
<?php elseif ($stage === 'signup_phone') : ?>
<form method="post"><?php $form('send-code'); ?>
<label class="form-label agw-field">Account type<select class="form-select" name="signup_type" required><option value="">Choose account type</option><option value="dealer" <?php selected($signupType,'dealer'); ?>>Dealership</option><?php if (\AutoAgora\Dealer\Support::privateSellersEnabled()) : ?><option value="private" <?php selected($signupType,'private'); ?>>Private seller</option><?php endif; ?></select></label>
<label class="form-label agw-field">Phone number<input class="form-control" name="signup_phone" type="tel" maxlength="30" required autocomplete="tel" placeholder="+357" value="<?php echo esc_attr($value('signup_phone')); ?>"></label>
<?php if (Signup::testing()) : ?><p class="text-secondary ag-connect-help">Use an approved test number and code from AutoAgora. No SMS is sent.</p><?php else : ?><div class="cf-turnstile ag-connect-help" data-sitekey="<?php echo esc_attr(TURNSTILE_SITE_KEY); ?>" data-action="connect_signup"></div><?php endif; ?>
<button class="btn btn-primary agw-login-submit"><?php echo Workspace::icon('arrow-right'); ?>Send code</button></form>
<?php elseif ($stage === 'signup_verify') : ?>
<p class="text-secondary">Enter the code for <?php echo esc_html($signup['phone'] ?? 'your phone'); ?>.</p>
<form method="post"><?php $form('verify-code'); ?><label class="form-label agw-field">Verification code<input class="form-control" name="signup_code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required></label><button class="btn btn-primary agw-login-submit"><?php echo Workspace::icon('shield-check'); ?>Verify phone</button></form>
<form method="post" class="ag-connect-secondary"><?php $form('signup'); ?><button class="btn btn-link">Change number or request a new code</button></form>
<?php elseif ($stage === 'signup_details') : ?>
<p class="text-secondary"><?php echo esc_html(($signup['type'] ?? '') === 'dealer' ? 'Dealership account' : 'Private seller account'); ?> · <?php echo esc_html($signup['phone'] ?? ''); ?></p>
<form method="post"><?php $form('create-account'); ?>
<?php if (($signup['type'] ?? '') === 'dealer') : ?><label class="form-label agw-field">Dealership name<input class="form-control" name="company_name" type="text" minlength="2" maxlength="120" required autocomplete="organization" value="<?php echo esc_attr($value('company_name')); ?>"></label><?php endif; ?>
<div class="ag-connect-name-grid"><label class="form-label agw-field">First name<input class="form-control" name="first_name" type="text" maxlength="80" required autocomplete="given-name" value="<?php echo esc_attr($value('first_name')); ?>"></label><label class="form-label agw-field">Last name<input class="form-control" name="last_name" type="text" maxlength="80" required autocomplete="family-name" value="<?php echo esc_attr($value('last_name')); ?>"></label></div>
<label class="form-label agw-field">Password<input class="form-control" name="signup_password" type="password" minlength="12" maxlength="128" required autocomplete="new-password"></label>
<label class="form-label agw-field">Confirm password<input class="form-control" name="signup_confirm" type="password" minlength="12" maxlength="128" required autocomplete="new-password"></label>
<button class="btn btn-primary agw-login-submit"><?php echo Workspace::icon('arrow-right'); ?>Create account</button></form>
<?php endif; ?>
<?php if ($stage !== 'signin') : ?><form method="post" class="ag-connect-secondary"><?php $form('signin-form'); ?><button class="btn btn-link">Already have an account? Sign in</button></form><?php endif; ?>
</section><p class="text-secondary agw-login-footer"><?php echo Workspace::icon('shield-check'); ?>Your password stays with AutoAgora.</p>
</main></body></html>
