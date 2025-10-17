<?php
require_once __DIR__ . '/includes/lang_loader.php';
?><!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Data Deletion Instructions</title>
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
			<a href="privacy_policy.php" class="text-decoration-none">&larr; Back to Privacy Policy</a>
		</div>
		<div class="card shadow-sm">
			<div class="card-body p-4 policy-content">
				<h1 class="h3 mb-3">Data Deletion Instructions</h1>
				<p class="text-muted mb-4">Last updated: <?= date('F j, Y') ?></p>

				<p>Our clients operate under contract-based agreements. At any time, you may request contract termination, data deletion, and account removal, subject to settlement of any outstanding fees and in accordance with legal obligations.</p>

				<h5 class="mt-4 section-title">How to Request Data Deletion</h5>
				<ol>
					<li>Contact us using the contract details provided to you at onboarding, or email us at <strong>admin@my-bookings.co.uk</strong>.</li>
					<li>Include your organisation name, workpoint/specialist identifiers (if applicable), and the scope of deletion requested (e.g., account removal, booking history, logs).</li>
					<li>We will verify your identity and contractual authority to act on behalf of the account holder.</li>
					<li>Upon verification, we will schedule and perform deletion and account closure within a reasonable timeframe, typically within 30 days, unless a shorter period is mandated.</li>
				</ol>

				<h5 class="mt-4 section-title">What We Delete</h5>
				<ul>
					<li>Active account credentials and access tokens</li>
					<li>Workpoint, specialist, and service records associated with the account</li>
					<li>Booking data and related operational records</li>
				</ul>

				<h5 class="mt-4 section-title">What We May Retain</h5>
				<p>Certain data may be retained where required by law, for tax/accounting purposes, or to resolve disputes. Such retention will be minimised and time-limited.</p>

				<h5 class="mt-4 section-title">Confirmation</h5>
				<p>After processing your request, we will provide written confirmation of account removal and the scope of data deleted.</p>

				<hr class="my-4">
				<p class="mb-0"><strong>Ownership Notice:</strong> All rights of this application belong to <strong>J.K Romeo Ltd</strong> and cannot be used without the owner’s accord.</p>
			</div>
		</div>
	</div>
</body>
</html> 