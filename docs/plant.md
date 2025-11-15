cek
ssa
sdasd


OPEN ROUTER API:

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




saya mau membuat website manajemen pegawai dengan fitur:

1. manajemen shift dengan fitur kalender seperti pada screenshot yang memiliki mode bulan, week, dan hari. manajemen shift ini memiliki alur: kepala toko atau cabang menentukan jadwal shift lalu mengirimkan email dan whatsaap ke semua karyawan yang telah diassign shift untuk melakukan konfirmasi shift. status seluruh karyawan pending secara default. seluruh karyawan yang telah mengkonfirmasi shift dengan status approved atau confirmed statusnya akan dilock pada shift tersebut. begitu juga dengan karyawan yang telah menolak shift dengan status reschedule, sakit, dan atau izin. bagi yang tidak memiliki shift di hari tersebut, dan menggantikan pegawai dengan status reschedule, sakit, dan atau izin dianggap overwork. kepala cabang di cabang x hanya bisa melihat dan mengubah jadwal shift di cabang x, begitu juga dengan y, z dan seterusnya. setelah proses assign shift oleh HR atau kepala cabang atau kepala toko, maka langsung akan kirim email dan WA ke user yang namanya ada di shift yang telah disubmit atau simpan untuk melakukan konfirmasi shift. setelah user atau pegawai melakukan konfirmasi shift dengan status confirmed atau approved akan kirim email dan WA ke HR atau kepala cabang atau kepala toko. jika sakit akan redirect ke halaman shift izin sakit (tidak tersedia di navbar) untuk mengisi data informasi perihal sakit apa, apakah punya surat dokter atau tidak? dan berapa hari tidak masuk (berapapun hari yang ditulis langsung akan mengubah assignment, overview, dan rekap absen)? lalu setelah itu disubmit barulah akan dikirim WA dan email ke HR dan kepala toko atau cabang. begitu juga dengan surat izin, sama dengan surat sakit. bedanya, surat izin akan melakukan autogenerate template surat izin yang telah tersedia. 

jika, ada kekurangan personil, pegawai cabang x dapat dipindahkan ke y, cabang y ke cabang x, cabang x ke cabang z, dan seterusnya. ada empat cabang: 1. adhyaksa; 2. BTP; 3. Citraland; 4. Kaori HQ (khusus admin).
ada tiga jenis shift:
1. pagi dari jam 07:00 wita - 15:00 wita (khusus cabang BTP 08:00 wita - 15:00 wita)
2. middle dari jam 13:00 wita - 21:00 wita (kecuali cabang BTP 12:00 wita - 20:00 wita)
3. sore dari jam 15:00 - 23:00.
*PENTING! SELURUH LOGIKA SHIFT HANYA BERLAKU BAGI USER, ADMIN TIDAK MEMBUTUHKAN SHIFT dan cabang Kaori HQ tidak butuh shift karena cabang khusus admin!

2. absensi berbasis lokasi yang mana kamera hanya akan muncul jika dan hanya jika berada di lokasi toko. kecuali admin dengan cabang Kaori HQ yang bisa melakukan absen di mana saja, tapi tetap cantumkan lokasi real time mereka di rekap absensi. pegawai cabang lain selain Kaori HQ tetap butuh absen di lokasi cabang asal mereka, kecuali jika ada pemindahan penempatan lokasi kerja sementara atau permanen, mereka dapat melakukannya di lokasi pemindahan. absensi ini berbasis foto juga. ada foto masuk dan foto keluar. user harus absen sesuai jadwal shift masuk dan keluar yang mereka punyai! wajib!. sementara admin tidak perlu karena mereka tidak memerlukan shift, dan mereka bisa absen kapanpun di rentang waktu jam 07:00 wita - 23.59 wita. batas absen keluar admin dan user biasa sampai jam 23:59 wita di hari mereka absen masuk. lewat dari itu, dianggap hadir namun dengan catatan lupa absen keluar dan berikan peringatan di halaman overview. ada 7 jenis status absen: 

1. hadir: untuk pegawai jika melakukan absen masuk dan absen keluar. untuk admin, jika mencukupi 8 jam kerja.
2. belum memenuhi kriteria: absen sebelum jam keluar untuk pegawai. belum mencukupi 8 jam untuk admin.
3. tidak hadir: untuk pegawai jika status shift confirmed atau approved dan tidak melakukan absen. untuk admin jika tidak absen keluar dan masuk. konsekuensi potong gaji pokok 50000/hari tidak hadir.
4. terlambat tanpa potongan (khusus pegawai): terlambat di bawah 20 menit dari waktu masuk. konsekuensi tidak ada. tapi mempengaruhi skor dan overview kinerja.
5. terlambat dengan potongan (khusus pegawai): jika shift dan terlambat di antara 20 menit - 39 menit 59 detik dari waktu masuk, konsekuensi potong tunjangan transportasi. terlambat 40 menit atau lebih dari waktu masuk, konsekuensi potong tunjangan transportasi dan makan. jika overwork, potong gaji jika terlambat 60 menit atau lebih, di bawah itu masuk ke poin 4.
6. izin: untuk user saat konfirmasi shift memilih izin dan redirect ke halaman surat izin atau mengakses langsung halaman surat izin melalui navbar (pisahkan token izin melalui navbar dan yang redirect dari saat konfirmasi shift) dan admin saat mengisi surat izin melalui navbar (karena tidak punya shift). konsekuensi, potong gaji pokok 50000 per hari izin dan overview kinerja izin. 
7. sakit: untuk user saat konfirmasi shift memilih sakit dan redirect ke halaman sakit. atau admin saat mengakses langsung halaman surat sakit admin. khusus admin wajib menyertakan surat sakit atau bukti surat yang telah ditandatangani oleh HR atau Finance atau Owner. khusus HR jika sakit ditandatangani oleh finance atau Owner. khusus untuk finance dan owner tidak perlu. konsekuensi, tidak ada potongan gaji, cuman tampilan overview dan rekap absen dengan status sakit. tanpa potongan. 

siklus absen ialah dari tanggal 28 bulan x - 28 bulan berikutnya. pegawai yang masuk dan atau diterima setelah awal siklus absen dimulai dianggap tidak hadir sebanyak hari setelah awal siklus absen yang dia lewati.

di akhir tiap absen keluar, akan ada pertanyaan: apakah kamu melakukan lembur? (kecuali shift terakhir). yang akan memunculkan dialog box berupa ya dan tidak. jika tidak, status tidak overwork. sebaliknya, jika ya maka status pending overwork dan akan langsung kirim email dan WA ke HR dan kepala cabang untuk mengkorfimasi status overwork. jika diterima, bonus overwork didapatkan. jika tidak, bonus tidak didapatkan dan status overwork menjadi declined.

fitur absen menggunakan lokasi (untuk unlock kamera; kecuali cabang Kaori HQ); DAN FOTO

3. fitur rekap absensi. ada fitur rekap absensi harian, mingguan, bulanan dan tahunan. semua pekerja minimal punya 26 hari kerja. dan 4 hari libur. hari libur user atau pegawai ditentukan oleh tidak adanya jadwal shift. sementara admin, pasti selalu libur di hari minggu. dan perlu saya tegaskan bahwa admin tidak bisa overwork. di rekap absen ini, khusus admin tampilannya terbagi menjadi dua: 1. histori absen harian, mingguan, dan bulanan semua user (tampilkan data lengkap jam masuk, jam keluar, status keterlambatan, foto absen masuk, foto absen keluar, dan data lain yang berkaitan dengan proses absensi) 2. riwayat harian yang akan menunjukkan siapa saja yang belum dan sudah absen di hari itu (tampilkan jika dan hanya jika 1. sudah registrasi dan 2. punya shift (kecuali admin, hanya berlaku poin 1: sudah registrasi). khusus untuk user, mereka hanya bisa melihat rekap absensi harian, mingguan, bulanan, dan tahunan mereka sendiri. hadirkan fitur untuk export csv dan xslx per minggu, per user, per cabang, per bulan, dan per tahun.

4. surat izin autogenerate. akan saya sediakan template yang harus diisi otomatis oleh sistem. jadi pegawai dan admin hanya perlu mengisi: perihal, tanggal mulai izin, tanggal selesai izin, alasan izin (perihal auto capslock every first word; alasan izin auto lowercase). kemudian ada tandatangan digital untuk user dan admin yang mengajukan (untuk isi dan ubah tanda tangan pergi ke halaman profile).

5. autogenerate slip gaji. slip gaji terdiri dari komponen pasti dan tidak pasti. komponen pasti meliputi: gaji pokok (/bulan); tunjangan makan (/hari); tunjangan transportasi (/hari); tunjangan jabatan (/bulan); overwork (/jam; maksimal 8 jam). komponen tidak pasti (harus isi manual karena tiap bulan bisa berubah): hutang toko; kasbon; bonus marketing; insentif omset. slip gaji akan auto generate komponen pasti tiap tanggal 28. setelah autogenerate komponen pasti, kirim WA dan Email ke finance untuk mengisi komponen tidak pasti. hadirkan fitur import csv, xlsx, dan txt agar memudahkan proses. lalu, tambahkan pula tombol untuk bulk kirim email dan WA berisi slip gaji ke semua user yang slipgajinya ada.

lalu pastikan juga ada fitur di mana user biasa dan admin dapat melihat riwayat slip gaji mereka beserta komponennya(read only).

6. overview untuk menampilkan kehadiran, ketidakhadiran, lupa absen, sakit, izin, jumlah total hari kerja aktual, jumlah shift aktual. lalu ada juga overview khusus HRD di mana HRD dapat melihat overview semua orang atau per orang dan mendownloadnya dalam bentuk csv; diagram; png; dan lainnya . dengan adanya overview ini, adakan juga fitur pegawai terbaik berdasarkan posisi (pegawai terbaik mingguan dan bulanan).

7. semua user dan admin yang bisa register hanyalah mereka yang sudah terdaftar sebagai whitelist (bagian dari pegawai). jika tidak mereka tidak bisa register. adakan juga fitur untuk tambah pegawai ke dalam whitelist secara satuan dan secara bulking dengan cara import csv, xslx, dan txt.

8. fitur profile: readonly (posisi, nama, dan outlet) password, username, email, nomor wa, tandatangan, dan foto profil bisa diubah.

9. ada tiga jenis user: admin, superadmin, dan user. superadmin adalah superadmin, finance, dan owner; admin adalah HR, akuntan, scm, kepala toko dan marketing. user adalah barista, kitchen, dan server.

10. ada fitur untuk menambah dan mengedit jabatan atau posisi sekaligus dengan status user/adminnya yang hanya bisa diakses oleh superadmin.

11. superadmin DAN ADMIN tidak butuh shift (KECUALI KEPALA TOKO).




asda
Sebagai ahli simplifyer, setelah menelaah file kaori_hr_test.sql serta hasil analisis sebelumnya yang membahas perbandingan MySQL dan MongoDB serta identifikasi tabel yang redundan, berikan penilaian apakah tetap menggunakan MySQL atau lebih baik beralih ke MongoDB. Sertakan rekomendasi mengenai tabel mana yang dapat digabungkan atau dihilangkan tanpa mengorbankan fungsionalitas inti sistem HR, serta jelaskan secara ringkas alasan teknis terkait keandalan ACID, relasi antar tabel, indeksasi, performa query, dan skalabilitas.

saya akan jelaskan fungsi tabel satu per satu untuk kamu tentukan.

1. absensi: untuk menampung data seputar absen
2. absensi error log: untuk tampung error log absen
3. 