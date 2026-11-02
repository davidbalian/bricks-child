<?php
/** Branded, server-rendered connection steps; deliberately no account IDs/contact details in matches. */
defined('ABSPATH') || exit;
use AutoAgora\Dealer\Workspace;
$assets = get_stylesheet_directory_uri() . '/includes/dealer-integration/assets/';
$titles = ['find'=>'Find your dealership','matches'=>'Choose your dealership','signin'=>'Sign in to AutoAgora','request'=>'Request access','requested'=>'Request received'];
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
</head><body class="agw agw-signin ag-buttons ag-connect"><main class="agw-login-shell">
<img class="agw-login-logo" src="<?php echo esc_url($assets . 'autoagora-logo.png'); ?>" width="160" height="34" alt="AutoAgora">
<section class="card card-body agw-login-panel">
<?php if (wp_get_environment_type() !== 'production') : ?><span class="badge bg-azure-lt ag-connect-environment"><?php echo wp_get_environment_type() === 'local' ? 'Local test' : 'Staging test'; ?></span><?php endif; ?>
<h1><?php echo esc_html($titles[$stage]); ?></h1>
<?php if ($clientName && $stage !== 'requested') : ?><p class="text-secondary">Connect with <?php echo esc_html($clientName); ?>.</p><?php endif; ?>
<?php if ($accountType === 'private' && $stage !== 'requested') : ?><p class="text-secondary">Private seller account</p><?php endif; ?>
<?php if ($error) : ?><div class="alert alert-danger" role="alert"><?php echo esc_html($error); ?></div><?php endif; ?>
<?php if ($stage === 'find') : ?>
<form method="post"><?php $form('find'); ?>
<label class="form-label agw-field">Dealership name<input class="form-control" name="dealership_name" type="text" minlength="3" maxlength="120" required autocomplete="organization" value="<?php echo esc_attr($name); ?>" placeholder="e.g. Demo Motors"></label>
<label class="form-label agw-field">City <span class="text-secondary">(optional)</span><input class="form-control" name="dealership_city" type="text" maxlength="80" autocomplete="address-level2" value="<?php echo esc_attr($city); ?>"></label>
<button class="btn btn-primary agw-login-submit"><?php echo Workspace::icon('building'); ?>Find dealership</button></form>
<form method="post" class="ag-connect-secondary"><?php $form('direct'); ?><button class="btn btn-link">Already have login details? Sign in</button></form>
<?php elseif ($stage === 'matches') : ?>
<p class="text-secondary">Choose a possible match, then sign in to confirm ownership.</p>
<div class="ag-connect-matches">
<?php foreach ($matches as $token => $match) : ?>
<form method="post" class="ag-connect-match"><?php $form('choose'); ?><input type="hidden" name="candidate" value="<?php echo esc_attr($token); ?>">
<span class="ag-connect-match-label"><strong><?php echo esc_html($match['name']); ?></strong><?php if ($match['city']) : ?><span class="text-secondary"><?php echo esc_html($match['city']); ?></span><?php endif; ?></span>
<button class="btn btn-primary">Continue <?php echo Workspace::icon('arrow-right'); ?></button></form>
<?php endforeach; ?></div>
<form method="post" class="ag-connect-secondary"><?php $form('request-form'); ?><input type="hidden" name="dealership_name" value="<?php echo esc_attr($name); ?>"><button class="btn btn-link">My dealership isn’t listed</button></form>
<?php elseif ($stage === 'signin') : ?>
<?php if ($selected) : ?><div class="alert alert-secondary ag-connect-selected"><?php echo Workspace::icon('building'); ?><span><?php echo esc_html($selected['name']); ?></span></div><?php endif; ?>
<form method="post"><?php $form('signin'); ?>
<label class="form-label agw-field">Phone or username<input class="form-control" name="dealer_login" type="text" required maxlength="191" autocomplete="username" autocapitalize="none" spellcheck="false"></label>
<label class="form-label agw-field">Password<input class="form-control" name="dealer_password" type="password" required maxlength="4096" autocomplete="current-password"></label>
<button class="btn btn-primary agw-login-submit"><?php echo Workspace::icon('shield-check'); ?>Sign in and continue</button></form>
<?php if ($authorization) : ?><form method="post" class="ag-connect-secondary"><?php $form('request-form'); ?><button class="btn btn-link"><?php echo $accountType === 'private' ? 'I need access to my account' : 'I need access to this dealership'; ?></button></form><?php else : ?><p class="ag-connect-help">Need login details? Contact AutoAgora or start again from Postaki to request access.</p><?php endif; ?>
<?php elseif ($stage === 'request') : ?>
<p class="text-secondary">We’ll check the existing account before arranging access.</p>
<form method="post"><?php $form('request'); ?>
<label class="form-label agw-field"><?php echo $accountType === 'private' ? 'Your full name' : 'Dealership name'; ?><input class="form-control" name="dealership_name" type="text" required minlength="3" maxlength="120" autocomplete="<?php echo $accountType === 'private' ? 'name' : 'organization'; ?>" value="<?php echo esc_attr($name); ?>"></label>
<label class="form-label agw-field">City <span class="text-secondary">(optional)</span><input class="form-control" name="dealership_city" type="text" maxlength="80" autocomplete="address-level2" value="<?php echo esc_attr($city); ?>"></label>
<?php if ($accountType !== 'private') : ?><label class="form-label agw-field">Your name<input class="form-control" name="contact_name" type="text" required maxlength="120" autocomplete="name" value="<?php echo esc_attr(isset($_POST['contact_name']) && is_string($_POST['contact_name']) ? sanitize_text_field(wp_unslash($_POST['contact_name'])) : ''); ?>"></label><?php endif; ?>
<label class="form-label agw-field">Contact email or phone<input class="form-control" name="contact" type="text" required maxlength="191" autocomplete="off"></label>
<button class="btn btn-primary agw-login-submit">Request access <?php echo Workspace::icon('arrow-right'); ?></button></form>
<?php elseif ($stage === 'requested') : ?>
<div class="ag-connect-success"><?php echo Workspace::icon('circle-check'); ?></div><p>AutoAgora will review your request and contact you with the next step.</p>
<p class="text-secondary">Reference: <strong><?php echo esc_html($reference); ?></strong></p><p class="ag-connect-help">After access is arranged, click “Connect to AutoAgora” in <?php echo esc_html($clientName); ?> again.</p>
<?php endif; ?>
</section><p class="agw-login-footer"><?php echo Workspace::icon('shield-check'); ?>Your password stays with AutoAgora.</p></main></body></html>
