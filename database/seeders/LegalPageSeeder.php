<?php

namespace Database\Seeders;

use App\Models\LegalPage;
use Illuminate\Database\Seeder;

class LegalPageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = $this->pages();

        foreach ($pages as $i => $page) {
            LegalPage::updateOrCreate(
                ['slug' => $page['slug']],
                array_merge($page, ['sort_order' => $i, 'is_system' => true])
            );
        }
    }

    /** @return array<int, array<string, string>> */
    private function pages(): array
    {
        return [
            [
                'slug' => 'terms',
                'title' => 'Terms of Service',
                'subtitle' => null,
                'content_updated_at' => now()->format('Y-m-d'),
                'content' => $this->terms(),
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'subtitle' => null,
                'content_updated_at' => now()->format('Y-m-d'),
                'content' => $this->privacy(),
            ],
            [
                'slug' => 'cookies',
                'title' => 'Cookie Policy',
                'subtitle' => null,
                'content_updated_at' => now()->format('Y-m-d'),
                'content' => $this->cookies(),
            ],
            [
                'slug' => 'dmca',
                'title' => 'DMCA',
                'subtitle' => null,
                'content_updated_at' => now()->format('Y-m-d'),
                'content' => $this->dmca(),
            ],
        ];
    }

    private function terms(): string
    {
        return <<<'MD'
## 1. Acceptance of Terms

By accessing or using the Platform, you agree to be bound by these Terms of Service. If you do not agree, please do not use the Platform.

## 2. Eligibility

You must be at least **13 years old** to use the Platform. By registering, you confirm that you meet this requirement.

## 3. User Accounts

- You are responsible for maintaining the security of your account and password.
- You must notify us immediately of any unauthorized use of your account.
- We reserve the right to terminate accounts that violate these terms.

## 4. Prohibited Conduct

You agree not to:

- Post content that is illegal, abusive, threatening, or harassing.
- Attempt to gain unauthorized access to other accounts or systems.
- Use the Platform to distribute spam or malware.
- Impersonate other users or staff members.

## 5. User-Generated Content

By posting content on the Platform, you grant us a non-exclusive, worldwide, royalty-free license to display and distribute that content in connection with the Platform.

## 6. Intellectual Property

All Platform software, design, and branding are the intellectual property of the Platform and may not be reproduced without permission.

## 7. Limitation of Liability

The Platform is provided **"as is"** without warranties of any kind. We are not liable for any indirect, incidental, or consequential damages arising from your use of the Platform.

## 8. Changes to Terms

We may update these Terms at any time. Continued use of the Platform after changes constitutes acceptance of the new Terms.

## 9. Contact

If you have questions about these Terms, please contact the site administrators.
MD;
    }

    private function privacy(): string
    {
        return <<<'MD'
## 1. Information We Collect

We collect the following information when you use the Platform:

- **Account data** — name, username, email address, and password (stored as a secure hash).
- **Profile data** — avatar, bio, location, and other optional fields you provide.
- **Usage data** — pages visited, actions taken, and timestamps of activity.
- **Technical data** — IP address, browser type, and device information for security purposes.

## 2. How We Use Your Information

- To provide and improve the Platform's features and services.
- To send transactional emails (e.g., password resets, notification digests).
- To enforce our Terms of Service and protect the safety of users.
- To display your public profile to other members.

## 3. Data Sharing

We do **not** sell or rent your personal data to third parties. We may share data with:

- Service providers that help us operate the Platform (e.g., hosting).
- Law enforcement when required by applicable law.

## 4. Cookies

We use cookies to keep you logged in and to improve your experience. See our [Cookie Policy](/legal/cookies) for details.

## 5. Data Retention

We retain your account data for as long as your account is active. You may request deletion of your account and data at any time from your account settings.

## 6. Your Rights

Depending on your location, you may have rights to:

- Access, correct, or delete your personal data.
- Object to or restrict certain processing.
- Request a copy of your data in a portable format.

To exercise these rights, contact the site administrators.

## 7. Security

We use industry-standard security measures, including password hashing, HTTPS, and session management, to protect your data.

## 8. Changes to This Policy

We may update this Privacy Policy periodically. We will notify registered users of significant changes.
MD;
    }

    private function cookies(): string
    {
        return <<<'MD'
## What Are Cookies?

Cookies are small text files placed on your device by a website. They are widely used to make websites work, or work more efficiently, and to provide information to the site owners.

## Cookies We Use

### Essential Cookies

| Cookie | Purpose | Duration |
|--------|---------|----------|
| `session` | Keeps you logged in during your visit | Session |
| `XSRF-TOKEN` | Protects against cross-site request forgery attacks | Session |
| `theme` | Remembers your light/dark mode preference | 1 year |
| `locale` | Remembers your language preference | 1 year |

These cookies are strictly necessary for the Platform to function. You cannot opt out of them while using the Platform.

### Functional Cookies

These cookies enhance your experience but are not essential:

| Cookie | Purpose | Duration |
|--------|---------|----------|
| `remember_token` | Keeps you logged in across browser sessions (if "Remember me" is checked) | 30 days |

## Managing Cookies

You can control cookies through your browser settings. Note that disabling essential cookies will prevent you from logging in or using certain features.

Most browsers allow you to:
- View what cookies are stored.
- Delete cookies individually or all at once.
- Block cookies from specific or all websites.

Refer to your browser's help documentation for instructions.

## Changes to This Policy

We may update this Cookie Policy from time to time. The "Last updated" date at the top of this page reflects the most recent revision.
MD;
    }

    private function dmca(): string
    {
        return <<<'MD'
## Overview

This Digital Millennium Copyright Act (DMCA) Policy explains how we respond to claims of copyright infringement on the Platform. We respect the intellectual property rights of others and expect our users to do the same.

## Reporting Copyright Infringement

If you believe that content available on the Platform infringes your copyright, you may submit a DMCA takedown notice to our designated agent. Your notice must include **all** of the following:

1. **Your signature** — A physical or electronic signature of the copyright owner or a person authorized to act on their behalf.
2. **Identification of the copyrighted work** — A description of the copyrighted work you claim has been infringed.
3. **Identification of the infringing material** — A description of the content you believe infringes your copyright, with enough detail for us to locate it (e.g., URL).
4. **Your contact information** — Your name, address, telephone number, and email address.
5. **Good faith statement** — A statement that you have a good faith belief that use of the material in the manner complained of is not authorized by the copyright owner, its agent, or the law.
6. **Accuracy statement** — A statement that the information in your notice is accurate, and under penalty of perjury, that you are authorized to act on behalf of the copyright owner.

Submit your notice to the site administrators via the contact page or by email.

## Counter-Notice

If you believe content was removed in error, you may submit a counter-notice including:

1. Your physical or electronic signature.
2. Identification of the removed material and its location before removal.
3. A statement under penalty of perjury that you have a good faith belief the material was removed by mistake or misidentification.
4. Your name, address, phone number, email, and consent to jurisdiction.

## Repeat Infringers

We reserve the right to terminate the accounts of users who are determined to be repeat infringers in appropriate circumstances.

## Disclaimer

Nothing in this policy constitutes legal advice. If you are unsure whether content infringes a copyright, consult a qualified attorney.
MD;
    }
}
