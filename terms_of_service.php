<?php
// Terms of Service page (no login required)
require_once __DIR__ . '/includes/lang_loader.php';
?><!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - Calendar Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f7f7fb; }
        .container-narrow { max-width: 840px; }
        .card { border-radius: 12px; border: 1px solid #eaecef; }
        .policy-content p { line-height: 1.65; }
        .policy-content ul { margin-left: 1.5rem; }
        .policy-content li { margin-bottom: 0.5rem; }
        .section-title { font-size: 1.1rem; font-weight: 600; color: #333; border-left: 4px solid #667eea; padding-left: .5rem; margin-top: 2rem; }
        .highlight { background: #f8f9fa; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
    </style>
</head>
<body>
    <div class="container container-narrow py-4">
        <div class="mb-4">
            <a href="login.php" class="text-decoration-none">&larr; Back to Login</a>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-4 policy-content">
                <h1 class="h3 mb-3">Terms of Service</h1>
                <p class="text-muted mb-4">Last updated: <?= date('F j, Y') ?></p>
                
                <p>Welcome to the Calendar Booking System. By using our service, you agree to these Terms of Service. Please read them carefully.</p>
                
                <h5 class="mt-4 section-title">1. Acceptance of Terms</h5>
                <p>By accessing and using this calendar booking system, you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to these terms, please do not use our service.</p>
                
                <h5 class="mt-4 section-title">2. Description of Service</h5>
                <p>Our calendar booking system provides:</p>
                <ul>
                    <li>Multi-channel appointment booking (voice, SMS, WhatsApp, Messenger, web interface, webhooks)</li>
                    <li>AI-powered appointment management and scheduling</li>
                    <li>Multi-organization and multi-specialist support</li>
                    <li>Real-time calendar synchronization</li>
                    <li>Administrative dashboard for managing bookings</li>
                </ul>
                
                <h5 class="mt-4 section-title">3. User Accounts</h5>
                <p>To use our service, you must:</p>
                <ul>
                    <li>Provide accurate, current, and complete information during registration</li>
                    <li>Maintain the security of your password and account</li>
                    <li>Promptly update your account information if it changes</li>
                    <li>Accept responsibility for all activities under your account</li>
                    <li>Notify us immediately of any unauthorized use</li>
                </ul>
                
                <h5 class="mt-4 section-title">4. Acceptable Use Policy</h5>
                <p>You agree NOT to use the service to:</p>
                <ul>
                    <li>Violate any laws or regulations</li>
                    <li>Submit false or misleading information</li>
                    <li>Interfere with or disrupt the service or servers</li>
                    <li>Attempt to gain unauthorized access to any portion of the service</li>
                    <li>Use automated systems or software to extract data</li>
                    <li>Transmit spam, chain letters, or other unsolicited email</li>
                    <li>Impersonate another person or entity</li>
                </ul>
                
                <h5 class="mt-4 section-title">5. Booking and Appointment Policies</h5>
                <ul>
                    <li>All bookings are subject to availability</li>
                    <li>Users are responsible for providing accurate booking information</li>
                    <li>Cancellations must be made according to the organization's policy</li>
                    <li>No-shows may result in service restrictions</li>
                </ul>
                
                <h5 class="mt-4 section-title">6. Data Usage and Privacy</h5>
                <p>Your use of our service is also governed by our <a href="privacy_policy.php">Privacy Policy</a>. By using the service, you consent to:</p>
                <ul>
                    <li>Collection and processing of appointment data</li>
                    <li>Storage of necessary information for service delivery</li>
                    <li>Communication regarding appointments and service updates</li>
                </ul>
                
                <h5 class="mt-4 section-title">7. Intellectual Property</h5>
                <p>The service and its original content, features, and functionality are owned by us and are protected by international copyright, trademark, patent, trade secret, and other intellectual property laws.</p>
                
                <h5 class="mt-4 section-title">8. Service Availability</h5>
                <ul>
                    <li>We strive for 99.9% uptime but do not guarantee uninterrupted service</li>
                    <li>We reserve the right to modify or discontinue features with notice</li>
                    <li>Scheduled maintenance will be communicated in advance when possible</li>
                </ul>
                
                <h5 class="mt-4 section-title">9. Limitation of Liability</h5>
                <div class="highlight">
                    <p class="mb-0"><strong>To the maximum extent permitted by law, we shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use or inability to use the service.</strong></p>
                </div>
                
                <h5 class="mt-4 section-title">10. Indemnification</h5>
                <p>You agree to indemnify, defend, and hold harmless the service provider from any claims, liabilities, damages, losses, and expenses arising from your use of the service or violation of these terms.</p>
                
                <h5 class="mt-4 section-title">11. Termination</h5>
                <p>We may terminate or suspend your account and access to the service immediately, without prior notice, for:</p>
                <ul>
                    <li>Breach of these Terms of Service</li>
                    <li>At your request for account deletion</li>
                    <li>Extended period of inactivity</li>
                    <li>Fraudulent or illegal activities</li>
                </ul>
                
                <h5 class="mt-4 section-title">12. Changes to Terms</h5>
                <p>We reserve the right to modify these terms at any time. We will notify users of any material changes via email or service notification. Continued use of the service after changes constitutes acceptance of the new terms.</p>
                
                <h5 class="mt-4 section-title">13. Governing Law</h5>
                <p>These Terms shall be governed and construed in accordance with the laws of the United Kingdom, without regard to its conflict of law provisions.</p>
                
                <h5 class="mt-4 section-title">14. Contact Information</h5>
                <p>For questions about these Terms of Service, please contact us at:</p>
                <div class="highlight">
                    <p class="mb-0">
                        <strong>Email:</strong> admin@my-bookings.co.uk<br>
                        <strong>Phone:</strong> +44 7504 128961
                    </p>
                </div>
                
                <hr class="my-4">
                <p class="mb-0 text-center text-muted">
                    By using our service, you acknowledge that you have read, understood, and agree to be bound by these Terms of Service.
                </p>
            </div>
        </div>
    </div>
</body>
</html>