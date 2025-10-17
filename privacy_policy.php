<?php
// Minimal public Privacy Policy page (no login required)
require_once __DIR__ . '/includes/lang_loader.php';
?><!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Privacy Policy</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		body { background: #f7f7fb; }
		.container-narrow { max-width: 840px; }
		.card { border-radius: 12px; border: 1px solid #eaecef; }
		.policy-content p { line-height: 1.65; }
		.section-title { font-size: 1.1rem; font-weight: 600; color: #333; border-left: 4px solid #667eea; padding-left: .5rem; }
	</style>
</head>
<body>
	<div class="container container-narrow py-4">
		<div class="mb-4">
			<a href="login.php" class="text-decoration-none">&larr; Back to Login</a>
		</div>
		<div class="card shadow-sm">
			<div class="card-body p-4 policy-content">
				<h1 class="h3 mb-3">Privacy Policy</h1>
				<p class="text-muted mb-4">Last updated: <?= date('F j, Y') ?></p>

				<p>This application collects only the information necessary to provide calendar booking functionality. We process data such as user account details, appointment information, and operational logs strictly for service delivery, troubleshooting, and security.</p>

				<h5 class="mt-4 section-title">Data We Process</h5>
				<ul>
					<li>Account information for authentication and role-based access</li>
					<li>Workpoint, specialist, service, and booking details</li>
					<li>Technical logs for reliability, auditing, and security</li>
				</ul>

				<h5 class="mt-4 section-title">How We Use Data</h5>
				<ul>
					<li>To operate and improve the booking system</li>
					<li>To provide support, prevent abuse, and ensure security</li>
					<li>To comply with legal obligations where applicable</li>
				</ul>

				<h5 class="mt-4 section-title">Your Choices</h5>
				<p>You may request access, correction, or deletion of your personal data where applicable. Contact the administrator for data requests to <strong>admin@my-bookings.co.uk</strong></p>

				<h5 class="mt-4 section-title">Information We Collect</h5>
				<p>Our app may collect limited information such as your name, phone number, email, or messages only to provide the requested functionality.</p>

				<h5 class="mt-4 section-title">How We Use Information</h5>
				<p>The collected information is used solely to operate and improve our services. We do not sell or rent your information to third parties.</p>

				<h5 class="mt-4 section-title">Data Sharing</h5>
				<p>We do not share your data with third parties, except when required by law.</p>

				<h5 class="mt-4 section-title">Data Retention</h5>
				<p>Your data is kept only as long as necessary to provide the service, then deleted securely.</p>

				<hr class="my-4">
				<p class="mb-2"><strong>Ownership Notice:</strong> All rights of this application belong to <strong>J.K Romeo Ltd</strong> and cannot be used without the owner’s accord.</p>
				<p class="mb-0"><a href="data_deletion.php">Request data deletion and account removal</a></p>
			</div>
		</div>
	</div>
</body>
</html> 