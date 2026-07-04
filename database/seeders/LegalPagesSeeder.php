<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Page;
use Illuminate\Database\Seeder;

class LegalPagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'title' => 'Terms of Service',
                'slug' => 'terms-of-service',
                'body' => $this->termsBody(),
            ],
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'body' => $this->privacyBody(),
            ],
            [
                'title' => 'Cookie Policy',
                'slug' => 'cookie-policy',
                'body' => $this->cookieBody(),
            ],
            [
                'title' => 'DMCA Policy',
                'slug' => 'dmca',
                'body' => $this->dmcaBody(),
            ],
        ];

        foreach ($pages as $data) {
            Page::firstOrCreate(['slug' => $data['slug']], [
                'title' => $data['title'],
                'slug' => $data['slug'],
                'body' => $data['body'],
                'status' => 'published',
                'published_at' => now(),
            ]);
        }

        // Create footer_legal menu
        $menu = Menu::firstOrCreate(['slug' => 'footer-legal'], [
            'name' => 'Footer Legal',
            'slug' => 'footer-legal',
            'location' => 'footer_legal',
        ]);

        if ($menu->items()->count() === 0) {
            $links = [
                ['label' => 'Terms of Service', 'url' => '/terms-of-service'],
                ['label' => 'Privacy Policy', 'url' => '/privacy-policy'],
                ['label' => 'Cookie Policy', 'url' => '/cookie-policy'],
                ['label' => 'DMCA', 'url' => '/dmca'],
            ];
            foreach ($links as $i => $link) {
                $menu->items()->create([
                    'label' => $link['label'],
                    'url' => $link['url'],
                    'target' => '_self',
                    'sort' => ($i + 1) * 10,
                ]);
            }
        }
    }

    private function termsBody(): string
    {
        return <<<'MD'
## 1. Acceptance of Terms

By accessing and using {site_name}, you accept and agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our service.

## 2. Use of Service

You agree to use {site_name} only for lawful purposes. You must not:

- Violate any applicable laws or regulations
- Post or transmit any harmful, offensive, or disruptive content
- Attempt to gain unauthorized access to any part of our systems
- Use the service to distribute spam or malware

## 3. User Accounts

You are responsible for maintaining the confidentiality of your account credentials. You agree to notify us immediately at {contact_email} if you suspect any unauthorized use of your account.

## 4. Intellectual Property

All content on {site_name}, including text, graphics, logos, and software, is the property of {site_name} and is protected by applicable intellectual property laws.

## 5. Disclaimer of Warranties

{site_name} is provided "as is" without any warranties, expressed or implied. We do not guarantee that the service will be uninterrupted, error-free, or free of viruses.

## 6. Limitation of Liability

To the fullest extent permitted by law, {site_name} shall not be liable for any indirect, incidental, special, or consequential damages arising from your use of the service.

## 7. Changes to Terms

We reserve the right to modify these terms at any time. Continued use of the service after changes constitutes acceptance of the new terms.

## 8. Contact

If you have questions about these Terms, please contact us at {contact_email}.
MD;
    }

    private function privacyBody(): string
    {
        return <<<'MD'
## 1. Information We Collect

We collect information you provide directly, such as when you create an account, including:

- **Account information:** name, email address, username
- **Profile data:** avatar, biography, preferences
- **Usage data:** pages visited, features used, interaction logs
- **Technical data:** IP address, browser type, device information

## 2. How We Use Your Information

We use the information we collect to:

- Provide, maintain, and improve our services
- Send you technical notices and support messages
- Respond to your comments and questions
- Monitor and analyze usage patterns to improve the experience

## 3. Information Sharing

We do not sell, trade, or transfer your personal information to third parties except:

- With your consent
- To comply with legal obligations
- To protect the rights and safety of {site_name} and its users

## 4. Data Retention

We retain your personal data for as long as your account is active or as needed to provide services. You may request deletion of your account and associated data at any time by contacting {contact_email}.

## 5. Cookies

We use cookies to enhance your experience. Please see our [Cookie Policy](/cookie-policy) for details.

## 6. Security

We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.

## 7. Your Rights

Depending on your location, you may have rights to:

- Access the personal data we hold about you
- Correct inaccurate data
- Request deletion of your data
- Object to processing of your data

To exercise these rights, contact us at {contact_email}.

## 8. Contact

For privacy-related inquiries, contact us at {contact_email}.
MD;
    }

    private function cookieBody(): string
    {
        return <<<'MD'
## What Are Cookies?

Cookies are small text files stored on your device when you visit a website. They help us provide a better experience by remembering your preferences and login state.

## Types of Cookies We Use

### Essential Cookies
These cookies are necessary for the website to function and cannot be switched off. They include session cookies that keep you logged in.

### Analytics Cookies
We may use analytics cookies to understand how visitors interact with our site. This data is aggregated and anonymous.

### Preference Cookies
These cookies remember your settings and preferences, such as your chosen theme (dark/light mode) and language.

## Managing Cookies

You can control cookies through your browser settings. Note that disabling certain cookies may affect the functionality of {site_name}.

Most browsers allow you to:
- View cookies stored on your device
- Delete cookies individually or in bulk
- Block cookies from specific sites

## Third-Party Cookies

Some pages may include content from third-party services (such as Steam or Discord for login). These services may set their own cookies subject to their respective privacy policies.

## Contact

If you have questions about our cookie usage, contact us at {contact_email}.
MD;
    }

    private function dmcaBody(): string
    {
        return <<<'MD'
## DMCA Notice and Takedown Policy

{site_name} respects the intellectual property rights of others and expects users to do the same. In accordance with the Digital Millennium Copyright Act (DMCA), we will respond to notices of alleged copyright infringement.

## Filing a DMCA Notice

If you believe content on {site_name} infringes your copyright, please send a notice to {contact_email} containing:

1. **Identification of the copyrighted work** you claim has been infringed
2. **Identification of the infringing material** and its location on our site (URL)
3. **Your contact information:** name, address, phone number, and email
4. **A statement** that you have a good faith belief that the use is not authorized
5. **A statement**, under penalty of perjury, that the information in the notice is accurate and you are authorized to act on behalf of the copyright owner
6. **Your physical or electronic signature**

## Counter-Notice

If you believe content was removed in error, you may file a counter-notice to {contact_email} containing:

1. Identification of the removed content and its location before removal
2. A statement, under penalty of perjury, that you have a good faith belief the material was removed by mistake
3. Your name, address, phone number, and consent to jurisdiction
4. Your physical or electronic signature

## Repeat Infringers

{site_name} reserves the right to terminate accounts of users who are repeat copyright infringers.

## Contact

DMCA notices should be sent to: {contact_email}
MD;
    }
}
