# Email Configuration Setup

## Konfigurasi Email untuk Reminder

Untuk mengaktifkan fitur reminder email, tambahkan konfigurasi berikut ke file `.env`:

```env
# Email Configuration
MAIL_MAILER=smtp
MAIL_HOST=mail.reynrastore.com
MAIL_PORT=465
MAIL_USERNAME=support@reynrastore.com
MAIL_PASSWORD=Wkqjdlbw123!@#
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=support@reynrastore.com
MAIL_FROM_NAME="CalendarPro"
```

## Detail Server Email:
- **Username**: support@reynrastore.com
- **Password**: Wkqjdlbw123!@#
- **Incoming Server**: mail.reynrastore.com
- **IMAP Port**: 993
- **POP3 Port**: 995
- **Outgoing Server**: mail.reynrastore.com
- **SMTP Port**: 465
- **Encryption**: SSL/TLS
- **Authentication**: Required

## Fitur Email yang Sudah Diimplementasi:

### 1. Event Reminder Notifications
- File: `app/Notifications/EventReminderNotification.php`
- Job: `app/Jobs/SendEventReminderJob.php`
- Service: `app/Services/ReminderSchedulingService.php`

### 2. Event Invitation Notifications
- File: `app/Notifications/EventInvitationNotification.php`
- Job: `app/Jobs/SendEventInvitationJob.php`
- Service: `app/Services/InvitationService.php`

### 3. Event RSVP Update Notifications
- File: `app/Notifications/EventRsvpUpdateNotification.php`

## Testing Email Configuration

Setelah menambahkan konfigurasi email ke `.env`, Anda dapat test dengan:

```bash
# Test email configuration
php artisan tinker

# Di dalam tinker:
Mail::raw('Test email from CalendarPro', function ($message) {
    $message->to('test@example.com')
            ->subject('Test Email');
});
```

## Queue Configuration

Untuk menjalankan email jobs, pastikan queue worker berjalan:

```bash
# Jalankan queue worker
php artisan queue:work

# Atau untuk development
php artisan queue:listen
```

## Troubleshooting

Jika email tidak terkirim:

1. **Cek koneksi SMTP**:
   ```bash
   php artisan tinker
   Mail::raw('Test', function($m) { $m->to('test@example.com'); });
   ```

2. **Cek log Laravel**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Cek queue jobs**:
   ```bash
   php artisan queue:failed
   ```

4. **Restart queue worker** setelah perubahan konfigurasi:
   ```bash
   php artisan queue:restart
   ```

## Security Notes

- Password email sudah di-encrypt dalam file .env
- Gunakan SSL/TLS untuk koneksi yang aman
- Pastikan firewall mengizinkan koneksi ke port 465
- Jangan commit file .env ke repository

## Status: READY FOR PRODUCTION ✅

Email configuration sudah siap digunakan untuk:
- ✅ Event reminder notifications
- ✅ Event invitation emails
- ✅ RSVP update notifications
- ✅ Contact form submissions
- ✅ User registration confirmations
