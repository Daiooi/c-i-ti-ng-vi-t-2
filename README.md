# He thong quan ly sinh vien thong minh bang PHP

Du an web PHP co ban ho tro giang vien quan ly sinh vien, lop hoc, diem so va chatbot AI tra cuu bang ngon ngu tu nhien. Mac dinh chatbot chay bang Ollama local (khong can the, khong can API key).

## Chuc nang

- Dang nhap giang vien, bao ve phien dang nhap va CSRF cho form.
- Quan ly sinh vien: them, sua, xoa, loc theo lop/trang thai/tu khoa.
- Quan ly lop hoc: them, sua, xoa, xem si so.
- Quan ly mon hoc: them, sua, xoa, loc theo loai mon va trang thai.
- Quan ly diem so: nhap diem qua trinh, giua ky, cuoi ky; tu tinh diem trung binh va xep loai.
- Tro ly AI: lay du lieu lien quan tu SQLite bang PDO, rut gon thanh context va goi Ollama local.
- Ho tro chuyen provider sang Gemini/OpenAI neu can.
- Neu AI provider bi loi, chatbot van fallback ve ket qua tra cuu cuc bo.

## Yeu cau

- PHP 8.0+.
- Extension `pdo_sqlite`, `curl`, `mbstring`, `openssl`.
- XAMPP tren may nay da co PHP tai `C:\xampp\php\php.exe`.

## Chay nhanh bang PHP built-in server

Mo terminal tai thu muc du an:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public
```

Sau do vao:

```text
http://127.0.0.1:8000
```

Tai khoan mau:

```text
Ten dang nhap: admin
Mat khau: admin123
```

## Cau hinh Ollama (mac dinh, khong can key)

1. Cai Ollama tu trang download: [https://ollama.com/download](https://ollama.com/download)
2. Tai model nhe (khuyen nghi cho may 16GB RAM):

```powershell
ollama pull qwen2.5:3b
```

3. Kiem tra service Ollama:

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:11434/api/generate" -Method Post -ContentType "application/json" -Body '{"model":"qwen2.5:3b","prompt":"xin chao","stream":false}'
```

Cach 2: sao chep file cau hinh:

```powershell
Copy-Item config\config.example.php config\config.php
```

Sau do sua `config/config.php`:

```php
'ai_provider' => 'ollama',
'ollama_base_url' => 'http://127.0.0.1:11434',
'ollama_model' => 'qwen2.5:3b',
'ollama_num_ctx' => 1024,
'ollama_num_predict' => 180,
'ollama_keep_alive' => '0',
```

## Cau hinh Gemini API (tuy chon)

Neu muon dung Gemini:

```php
'ai_provider' => 'gemini',
'gemini_api_key' => 'AIza...',
'gemini_model' => 'gemini-2.0-flash',
```

## Cau hinh OpenAI API (tuy chon)

Neu muon dung OpenAI:

```php
'ai_provider' => 'openai',
'openai_api_key' => 'sk-...',
'openai_model' => 'gpt-4.1-mini',
```

Khong commit file `config/config.php` vi file nay co the chua khoa API.

## Chay bang XAMPP Apache

1. Copy thu muc du an vao `C:\xampp\htdocs\student-manager`.
2. Mo XAMPP Control Panel va start Apache.
3. Truy cap `http://localhost/student-manager/public`.
4. Neu dung duong dan con, co the sua `base_url` trong `config/config.php` thanh `/student-manager/public`.

## Cau truc thu muc

```text
app/                Xu ly bootstrap, database, helper, AI provider
config/             Cau hinh mau va cau hinh rieng
database/           Thu muc du phong cho tai lieu SQL
public/             Web root: cac trang PHP (sinh vien/lop/mon/diem), assets, API chatbot
storage/            SQLite database tu tao khi mo web lan dau
```

## Ghi chu bao mat

- Mat khau duoc luu bang `password_hash`.
- Truy van SQL dung PDO prepared statements.
- Form them/sua/xoa co CSRF token.
- API key (neu dung Gemini/OpenAI) chi nam o server-side, khong dua ra trinh duyet.
- Chatbot chi nhan context du lieu da rut gon, khong tu y chay SQL tu noi dung nguoi dung.
