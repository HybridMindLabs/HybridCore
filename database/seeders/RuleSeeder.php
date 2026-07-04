<?php

namespace Database\Seeders;

use App\Models\Rule;
use Illuminate\Database\Seeder;

class RuleSeeder extends Seeder
{
    public function run(): void
    {
        Rule::updateOrCreate(
            ['slug' => 'general-terms'],
            [
                'title' => 'General Terms',
                'excerpt' => 'The core rules that apply to all members of the community.',
                'is_system' => true,
                'published' => true,
                'sort_order' => 0,
                'content' => $this->generalTerms(),
            ]
        );
    }

    private function generalTerms(): string
    {
        return <<<'MD'
## Welcome

These General Terms apply to every member of the community. By registering and participating, you agree to follow these rules at all times.

## 1. Respect & Conduct

- Treat all members with respect, regardless of their skill level, background, or opinion.
- Harassment, bullying, hate speech, or discrimination of any kind will not be tolerated.
- Keep discussions civil — disagreements are fine, personal attacks are not.

## 2. Communication

- Use appropriate language in all public channels and messages.
- Do not spam, flood chat, or send unsolicited messages to other members.
- Off-topic discussions should be kept to designated channels.

## 3. Usernames & Avatars

- Usernames and avatars must be appropriate and not impersonate other members or staff.
- Names containing offensive, racist, or misleading content will be changed without notice.

## 4. Cheating & Fair Play

- Cheating, exploiting bugs, or using unauthorized software is strictly prohibited.
- Any form of match-fixing or griefing will result in immediate action.

## 5. Account Responsibility

- You are responsible for all activity on your account.
- Sharing accounts is not permitted.
- Report suspicious activity to an administrator immediately.

## 6. Staff Instructions

- Follow instructions from administrators and moderators at all times.
- If you disagree with a decision, appeal through the proper channels — do not argue in public.

## 7. Consequences

Violations of these rules may result in warnings, temporary bans, or permanent removal from the community depending on severity and frequency.

## 8. Updates

These rules may be updated at any time. Members are responsible for staying informed of the current version.
MD;
    }
}
