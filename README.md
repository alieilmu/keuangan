# Dashboard Keuangan Personal

Aplikasi pencatatan arus kas personal: transaksi, anggaran bulanan dengan indikator
warna, tagihan berulang dengan pengingat, serta push notification ke browser desktop
dan mobile.

**Stack:** Laravel 13 - Inertia.js 3 - Vue 3 - Tailwind CSS 4 - Vite 8 - MySQL 8 - Redis - Docker.

---

## 1. Fitur

| Modul | Isi |
| --- | --- |
| Transaksi | CRUD pemasukan/pengeluaran yang otomatis menambah/memotong saldo akun, filter periode & kategori, import + export Excel dengan template baku |
| Anggaran | Plafon per kategori per bulan, agregasi pemakaian di sisi database, progress bar 4 tingkat warna, salin anggaran bulan sebelumnya |
| Tagihan | Catatan tagihan, tombol "Bayar Sekarang" (membuat transaksi + memotong saldo), pembatalan pembayaran, pengingat jatuh tempo |
| Notifikasi | Web Push (VAPID) + notifikasi in-app; dipicu scheduler harian dan evaluasi realtime saat transaksi dibuat |
| Kredit & Cicilan | Pinjaman jangka panjang (KPR, cicilan kendaraan): tagihan angsuran dibuat otomatis tiap bulan, progress "Bulan ke-12 dari 36", sisa tenor berkurang sendiri saat tagihannya dibayar, halaman detail berisi histori pembayaran per angsuran, plus tombol "Tagih Angsuran Berikutnya" untuk membayar di muka |
| Dokumen | Unggah PDF/gambar untuk berkas tagihan (wajib saat tagihan dibuat manual) dan nota pembayaran (opsional saat membayar). Berkas disimpan di disk privat dan hanya bisa dibuka pemiliknya |
| Dashboard | 3 hero card arus kas, pie chart alokasi pengeluaran, carousel tagihan yang bisa di-swipe, indikator anggaran |
| Master data | Akun dana & kategori dengan pemilih warna: palet 10 warna x 5 kecerahan (50 pilihan cepat) plus input RGB/hex bebas |

---

## 2. Menjalankan dengan Docker (VPS Ubuntu)

```bash
git clone <repo> keuangan && cd keuangan

cp .env.docker.example .env
nano .env                       # isi APP_URL, DB_PASSWORD, DB_ROOT_PASSWORD, APP_PORT

docker compose build
docker compose run --rm app php artisan key:generate --force
docker compose run --rm app php artisan webpush:vapid --show   # salin ke VAPID_* di .env

docker compose up -d
docker compose run --rm app php artisan db:seed --force        # buat user Suami & Istri
```

Aplikasi tersedia di `http://IP-VPS:8080` (atau nilai `APP_PORT` yang Anda pilih).

### Mapping port

Nginx di dalam container **selalu** mendengarkan port `8080`, jadi tidak pernah bentrok
dengan service lain di VPS. Yang diubah hanya sisi host:

```env
APP_PORT=9200      # http://IP-VPS:9200 -> container:8080
```

Port MySQL sengaja tidak dipublikasikan ke host. Buka blok `ports:` pada service `db`
di `docker-compose.yml` hanya bila benar-benar dibutuhkan.

### Container yang berjalan

| Service | Peran |
| --- | --- |
| `app` | Nginx + PHP-FPM (port 8080), menjalankan migrasi saat start (`RUN_MIGRATIONS=true`) |
| `queue` | `queue:work` - mengirim notifikasi database & web push |
| `scheduler` | `schedule:work` - pengganti cron host untuk `credits:generate-bills`, `bills:remind` & `budgets:check` |
| `db` | MySQL 8.4 |
| `redis` | cache, session, dan queue |

### Reverse proxy (opsional, untuk HTTPS)

Arahkan Nginx/Caddy/Traefik milik VPS ke `127.0.0.1:${APP_PORT}`. Aplikasi sudah
memakai `trustProxies(at: '*')` sehingga `APP_URL` https terdeteksi dengan benar.

---

## 3. Menjalankan secara lokal (tanpa Docker)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite      # default DB_CONNECTION=sqlite
php artisan migrate --seed
npm run build                       # atau: npm run dev
php artisan serve
```

### Akun login hasil seeder

| Nama | Email | Password |
| --- | --- | --- |
| Suami | `suami@keuangan.test` | `241025` |
| Istri | `istri@keuangan.test` | `241025` |

Kedua akun berdiri sendiri: akun dana, kategori, anggaran, tagihan, dan transaksi
milik satu user tidak terlihat oleh user lain.

Seeder menyiapkan 3 akun dana (Dompet Tunai, Rekening Bank, e-Wallet) bersaldo `0`
dan 12 kategori bawaan untuk masing-masing user. **Tidak ada data transaksi,
anggaran, maupun tagihan** - semuanya dimulai dari kosong.

Halaman **Daftar** juga menyiapkan akun & kategori bawaan yang sama untuk user baru.
Catatan: pendaftaran mandiri mensyaratkan password minimal 8 karakter
(`app/Http/Controllers/Auth/RegisteredUserController.php`), sedangkan password
`241025` di atas ditetapkan lewat seeder sehingga tidak melewati aturan itu.

---

## 4. Push notification

1. `php artisan webpush:vapid --show`, lalu isi `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`,
   dan `VAPID_SUBJECT` di `.env`.
2. Buka aplikasi lewat **HTTPS** (atau `http://localhost` untuk pengembangan).
3. Klik ikon lonceng - **Aktifkan notifikasi**, lalu izinkan pada prompt browser.

Notifikasi dikirim ketika:

- pemakaian anggaran menembus **70%** dan **100%** (realtime saat transaksi dibuat,
  plus pengecekan harian sebagai jaring pengaman);
- tagihan memasuki jendela `remind_days_before` atau sudah melewati jatuh tempo.

Kolom `budgets.notified_threshold` dan `bills.reminded_on` mencegah notifikasi berulang.

---

## 5. Import & export Excel

1. **Template** - mengunduh `template-import-transaksi.xlsx` berisi sheet `Transaksi`
   (header baku + 2 baris contoh) dan sheet `Panduan` (daftar nama akun & kategori valid).
2. **Import** - hanya sheet pertama yang dibaca. Setiap baris dinormalisasi
   (tanggal serial Excel, `1.250.000`, sinonim `pemasukan`/`pengeluaran`) lalu divalidasi.
   Baris yang gagal dilaporkan beserta nomor barisnya tanpa membatalkan baris lain.
   Kategori yang belum ada dibuat otomatis; nama akun harus cocok.
   Setelah import, saldo seluruh akun dihitung ulang dari histori.
3. **Export** - kolomnya identik dengan template, sehingga hasil export bisa
   langsung dipakai untuk import balik.

---

## 6. Perintah artisan khusus

```bash
php artisan credits:generate-bills  # buat tagihan angsuran kredit bulan berjalan
php artisan bills:remind      # pengingat tagihan jatuh tempo
php artisan budgets:check     # cek anggaran menembus 70% / 100%
php artisan schedule:list     # lihat jadwal yang terdaftar
```
