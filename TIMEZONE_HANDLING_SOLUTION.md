# Timezone Handling Solution

## Masalah yang Diselesaikan

User menanyakan mengapa backend menggunakan UTC untuk `start_at` dan `end_at` event. Setelah analisis, ditemukan bahwa:

1. **Backend menggunakan UTC** - Ini adalah praktik yang BENAR untuk konsistensi sistem dan reminder
2. **Frontend perlu konversi timezone** - Data UTC perlu dikonversi ke timezone user untuk display
3. **EventResource tidak melakukan konversi** - Data dikembalikan dalam UTC, bukan timezone user

## Solusi yang Diterapkan

### 1. Update EventResource.php

Menambahkan timezone conversion logic di `app/Http/Resources/EventResource.php`:

```php
// Get user's timezone, fallback to event's timezone, then to Asia/Jakarta
$userTimezone = $request->user()?->timezone ?? $this->timezone ?? 'Asia/Jakarta';

// Convert UTC times to user's timezone for display
$startAtInUserTz = $this->start_at ? $this->start_at->setTimezone($userTimezone) : null;
$endAtInUserTz = $this->end_at ? $this->end_at->setTimezone($userTimezone) : null;
```

### 2. Field yang Ditambahkan

**Waktu dalam Timezone User:**
- `start_at` - Waktu mulai dalam timezone user (ISO string)
- `end_at` - Waktu selesai dalam timezone user (ISO string)
- `date` - Tanggal dalam timezone user (Y-m-d format)
- `time` - Waktu mulai dalam timezone user (H:i format)
- `end_time` - Waktu selesai dalam timezone user (H:i format)

**Waktu UTC untuk Referensi:**
- `start_at_utc` - Waktu mulai dalam UTC (untuk API integrations)
- `end_at_utc` - Waktu selesai dalam UTC (untuk API integrations)

**Timezone Information:**
- `user_timezone` - Timezone yang digunakan untuk konversi
- `timezone` - Timezone asli event

**Enhanced Formatted Dates:**
- `formatted_start.full_date` - Format lengkap: "Monday, January 15, 2024"
- `formatted_start.time_12h` - Format 12 jam: "2:30 PM"
- `formatted_end.full_date` - Format lengkap untuk end date
- `formatted_end.time_12h` - Format 12 jam untuk end time

**Visual Enhancement:**
- `color` - Warna event berdasarkan calendar_id (konsisten)

### 3. Keuntungan Solusi Ini

**✅ Backend Tetap Menggunakan UTC:**
- Reminder system tetap akurat
- Database consistency terjaga
- Multi-timezone support optimal
- API integrations tidak terganggu

**✅ Frontend Mendapat Data yang Siap Pakai:**
- Waktu sudah dikonversi ke timezone user
- Multiple format tersedia (ISO, date, time, 12h, dll)
- Tidak perlu konversi manual di frontend
- Konsisten di seluruh aplikasi

**✅ Backward Compatibility:**
- Field lama tetap ada (`start_at_utc`, `end_at_utc`)
- Frontend existing masih bisa berfungsi
- Gradual migration possible

## Contoh Response API

**Sebelum (UTC):**
```json
{
  "id": "123",
  "title": "Meeting",
  "start_at": "2024-01-15T07:30:00.000000Z",
  "end_at": "2024-01-15T08:30:00.000000Z"
}
```

**Sesudah (User Timezone - Asia/Jakarta):**
```json
{
  "id": "123",
  "title": "Meeting",
  "start_at": "2024-01-15T14:30:00.000000+07:00",
  "end_at": "2024-01-15T15:30:00.000000+07:00",
  "start_at_utc": "2024-01-15T07:30:00.000000Z",
  "end_at_utc": "2024-01-15T08:30:00.000000Z",
  "date": "2024-01-15",
  "time": "14:30",
  "end_time": "15:30",
  "user_timezone": "Asia/Jakarta",
  "timezone": "Asia/Jakarta",
  "color": "#3B82F6",
  "formatted_start": {
    "date": "2024-01-15",
    "time": "14:30",
    "datetime": "2024-01-15 14:30:00",
    "human": "in 2 hours",
    "full_date": "Monday, January 15, 2024",
    "time_12h": "2:30 PM"
  },
  "formatted_end": {
    "date": "2024-01-15",
    "time": "15:30",
    "datetime": "2024-01-15 15:30:00",
    "human": "in 3 hours",
    "full_date": "Monday, January 15, 2024",
    "time_12h": "3:30 PM"
  }
}
```

## Frontend Usage

Frontend sekarang bisa langsung menggunakan:

```javascript
// Untuk display di calendar
const eventDate = event.date; // "2024-01-15"
const eventTime = event.time; // "14:30"

// Untuk display yang lebih user-friendly
const displayTime = event.formatted_start.time_12h; // "2:30 PM"
const displayDate = event.formatted_start.full_date; // "Monday, January 15, 2024"

// Untuk styling
const eventColor = event.color; // "#3B82F6"

// Untuk API calls yang butuh UTC
const utcStart = event.start_at_utc; // "2024-01-15T07:30:00.000000Z"
```

## Testing

Untuk testing, bisa menggunakan user dengan timezone berbeda:

```php
// User dengan timezone Jakarta
$user->timezone = 'Asia/Jakarta';
// Event UTC: 07:30 -> Display: 14:30

// User dengan timezone London  
$user->timezone = 'Europe/London';
// Event UTC: 07:30 -> Display: 07:30 (winter) atau 08:30 (summer)
```

## Kesimpulan

Solusi ini memberikan yang terbaik dari kedua dunia:
- **Backend**: Tetap menggunakan UTC untuk konsistensi sistem
- **Frontend**: Mendapat data yang sudah dikonversi ke timezone user
- **User Experience**: Waktu ditampilkan sesuai timezone user
- **System Reliability**: Reminder dan sistem lain tetap akurat
