<?php

declare(strict_types=1);


return [
    'labels' => [
        'description' => 'Beschreibung',
    ],
    'keys' => [
        'email_admin_mail' => 'Admin-Mail-Adresse',
        'email_your_name' => 'Dein angezeigter Name',
        'email_get_bcc_notification' => 'Channel-Notification Emails als BCC empfangen',
        'email_reminder_days' => 'Anzahl der Tage vor Ablauf für Erinnerungs-E-Mails',
        'expire_after_days' => 'Assignment Gültigkeit in Tagen',
        'assign_expire_cooldown_days' => 'Cooldown-Tage je (channel, video)',
        'ingest_inbox_absolute_path' => 'Inbox-Pfad für Videos (absolut)',
        'post_expiry_retention_weeks' => 'Aufbewahrungsfrist nach Ablauf (in Wochen)',
        'ffmpeg_bin' => 'Pfad zur FFmpeg-Binärdatei (z.B. /usr/bin/ffmpeg)',
        'ffmpeg_video_codec' => 'Video-Codec für Previews (z.B. libx264)',
        'ffmpeg_audio_codec' => 'Audio-Codec für Previews (z.B. aac)',
        'ffmpeg_preset' => 'FFmpeg-Preset für Geschwindigkeit/Qualität (z.B. medium)',
        'ffmpeg_crf' => 'CRF-Qualitätswert 0–51 (z.B. 23)',
        'ffmpeg_video_args' => 'Zusätzliche FFmpeg-Optionen (z.B. -movflags +faststart)',
    ],
];
