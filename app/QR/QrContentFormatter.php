<?php

namespace App\QR;

class QrContentFormatter
{
    public static function format(string $type, mixed $data): string
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                $data = $decoded;
            } else {
                return trim($data);
            }
        }

        switch (strtolower($type)) {
            case 'url':
                return trim($data['url'] ?? $data['destination'] ?? '');

            case 'text':
                return trim($data['text'] ?? $data['destination'] ?? '');

            case 'email':
                $email = trim($data['email'] ?? '');
                $subject = rawurlencode($data['subject'] ?? '');
                $body = rawurlencode($data['body'] ?? '');
                $query = [];
                if (!empty($subject)) $query[] = "subject={$subject}";
                if (!empty($body)) $query[] = "body={$body}";
                return 'mailto:' . $email . (!empty($query) ? '?' . implode('&', $query) : '');

            case 'phone':
                $phone = preg_replace('/[^\d\+]/', '', $data['phone'] ?? '');
                return 'tel:' . $phone;

            case 'sms':
                $phone = preg_replace('/[^\d\+]/', '', $data['phone'] ?? '');
                $message = rawurlencode($data['message'] ?? '');
                return 'smsto:' . $phone . ($message ? ':' . $message : '');

            case 'wifi':
                $ssid = str_replace([';', ':', '\\'], ['\;', '\:', '\\\\'], $data['ssid'] ?? '');
                $type = $data['encryption'] ?? 'WPA';
                $pass = str_replace([';', ':', '\\'], ['\;', '\:', '\\\\'], $data['password'] ?? '');
                $hidden = !empty($data['hidden']) ? 'true' : 'false';
                return "WIFI:S:{$ssid};T:{$type};P:{$pass};H:{$hidden};;";

            case 'vcard':
                $first = $data['first_name'] ?? '';
                $last = $data['last_name'] ?? '';
                $fullName = trim("{$first} {$last}");
                $org = $data['company'] ?? '';
                $title = $data['title'] ?? '';
                $phone = $data['phone'] ?? '';
                $email = $data['email'] ?? '';
                $website = $data['website'] ?? '';
                $address = $data['address'] ?? '';

                $vcard = "BEGIN:VCARD\nVERSION:3.0\n";
                $vcard .= "N:{$last};{$first};;;\n";
                $vcard .= "FN:{$fullName}\n";
                if ($org) $vcard .= "ORG:{$org}\n";
                if ($title) $vcard .= "TITLE:{$title}\n";
                if ($phone) $vcard .= "TEL;TYPE=WORK,VOICE:{$phone}\n";
                if ($email) $vcard .= "EMAIL:{$email}\n";
                if ($website) $vcard .= "URL:{$website}\n";
                if ($address) $vcard .= "ADR;TYPE=WORK:;;{$address};;;;\n";
                $vcard .= "END:VCARD";
                return $vcard;

            case 'location':
                $lat = $data['latitude'] ?? '0';
                $lng = $data['longitude'] ?? '0';
                return "geo:{$lat},{$lng}";

            case 'social':
                $platform = strtolower($data['platform'] ?? 'twitter');
                $handle = ltrim(trim($data['handle'] ?? ''), '@');
                return match ($platform) {
                    'twitter', 'x' => "https://x.com/{$handle}",
                    'instagram'    => "https://instagram.com/{$handle}",
                    'facebook'     => "https://facebook.com/{$handle}",
                    'linkedin'     => "https://linkedin.com/in/{$handle}",
                    'youtube'      => "https://youtube.com/@{$handle}",
                    'tiktok'       => "https://tiktok.com/@{$handle}",
                    'github'       => "https://github.com/{$handle}",
                    default        => $data['url'] ?? "https://{$platform}.com/{$handle}",
                };

            default:
                return is_string($data) ? $data : ($data['destination'] ?? '');
        }
    }
}
