# AI Media Verifier — المتحقق الذكي من الوسائط

منصة ويب عربية (RTL) تساعد المستخدمين على التحقق من صحة الصور ومقاطع الفيديو والتسجيلات الصوتية، والكشف عن احتمال التلاعب أو التزييف العميق فيها، باستخدام نماذج ذكاء اصطناعي **مفتوحة المصدر ومدرّبة مسبقًا**.

> ⚠️ **تنبيه أساسي:** النتائج تقديرات احتمالية صادرة عن نظام مساعد، وليست دليلًا قانونيًا أو جنائيًا قاطعًا. راجع [docs/ACADEMIC.md](docs/ACADEMIC.md) لحدود النظام الكاملة.

## المحتويات

- [البنية التقنية](#البنية-التقنية)
- [بنية المشروع](#بنية-المشروع)
- [التثبيت والتشغيل](#التثبيت-والتشغيل)
- [الحسابات التجريبية](#الحسابات-التجريبية)
- [الاختبارات](#الاختبارات)
- [تقييم نماذج الذكاء الاصطناعي](#تقييم-نماذج-الذكاء-الاصطناعي)
- [التوثيق الإضافي](#التوثيق-الإضافي)

## البنية التقنية

| الطبقة | التقنية |
|---|---|
| منصة الويب | Laravel 12، PHP 8.2، MySQL/MariaDB، Blade، Bootstrap 5 (RTL)، Alpine.js، Chart.js |
| خدمة الذكاء الاصطناعي | Python 3.12، FastAPI، PyTorch، Transformers، OpenCV، FFmpeg، Librosa |
| التقارير | mPDF (PDF بالعربية) وBaconQrCode (رمز QR) |
| الربط | REST API موثّق (`docs/API.md`) بمفتاح سري مشترك |

**نماذج الذكاء الاصطناعي المستخدمة (مفتوحة المصدر، لم تُدرَّب من الصفر):**

| الاستخدام | النموذج | الترخيص |
|---|---|---|
| كشف الوجوه المزيفة | [`prithivMLmods/Deep-Fake-Detector-v2-Model`](https://huggingface.co/prithivMLmods/Deep-Fake-Detector-v2-Model) | Apache-2.0 |
| كشف الصور المولّدة بالذكاء الاصطناعي | [`umm-maybe/AI-image-detector`](https://huggingface.co/umm-maybe/AI-image-detector) | CC-BY-4.0 |
| كشف الأصوات المولّدة/المستنسخة | [`MelodyMachine/Deepfake-audio-detection-V2`](https://huggingface.co/MelodyMachine/Deepfake-audio-detection-V2) | Apache-2.0 |

تُستخدم نماذج الصور نفسها في تحليل الفيديو (عبر أخذ عينات من الإطارات)، ونموذج الصوت في تحليل المسار الصوتي للفيديو إن وُجد. التفاصيل الكاملة وأسباب الاختيار في [docs/ACADEMIC.md](docs/ACADEMIC.md).

## بنية المشروع

```
filecode/
├── web/            منصة Laravel (الواجهة، قاعدة البيانات، لوحتا المستخدم والمدير)
├── ai-service/      خدمة FastAPI (محللات الصور والفيديو والصوت)
├── evaluation/      سكربتات تنزيل عينات التقييم وقياس المقاييس الحقيقية
└── docs/            التوثيق: API، ERD، البنية، حالات الاستخدام، التسلسل، الجانب الأكاديمي
```

## التثبيت والتشغيل

### المتطلبات

- PHP ≥ 8.2 وComposer وMySQL/MariaDB
- Node.js ≥ 18
- Python ≥ 3.11
- FFmpeg (لازم لتحليل الفيديو والصوت)

### 1) منصة الويب (Laravel)

```bash
cd web
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

عدّل `web/.env`: بيانات قاعدة البيانات (`DB_*`)، ومفتاح خدمة الذكاء الاصطناعي `AI_SERVICE_KEY` (ولّده بـ `php -r "echo bin2hex(random_bytes(32));"` وضع القيمة نفسها في `ai-service/.env`).

```bash
php artisan migrate --seed
php artisan serve --port=8010
```

في نافذة طرفية أخرى شغّل الطابور (لازم لتحليل الملفات في الخلفية):

```bash
php artisan queue:work
```

### 2) خدمة الذكاء الاصطناعي (FastAPI)

```bash
cd ai-service
python -m venv .venv
.venv/Scripts/activate      # على ويندوز؛ على لينكس/ماك: source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env         # ضع فيه AI_SERVICE_KEY نفسه الموجود في web/.env
uvicorn app.main:app --host 127.0.0.1 --port 8001
```

يفحص `GET /health` حالة الخدمة والنماذج دون مصادقة. تُنزَّل النماذج تلقائيًا من Hugging Face عند أول استخدام لكل واحد منها (~2 GB إجمالًا)، وتُخزَّن محليًا بعدها.

### 3) افتح المنصة

`http://127.0.0.1:8010`

## الحسابات التجريبية

| الدور | البريد الإلكتروني | كلمة المرور |
|---|---|---|
| مدير النظام | `admin@aimv.test` | `Admin@12345` |
| مستخدم مسجل | `user@aimv.test` | `User@12345` |

⚠️ غيّر كلمتي المرور هاتين أو احذف الحسابين قبل أي نشر فعلي. تُنشأ بيانات عرض تجريبية إضافية (موسومة بوضوح) تلقائيًا في بيئة `local` فقط.

## الاختبارات

```bash
# اختبارات Laravel (Feature وUnit) — 180+ اختبارًا
cd web && php artisan test

# اختبارات خدمة الذكاء الاصطناعي
cd ai-service && .venv/Scripts/python.exe -m pytest
```

## تقييم نماذج الذكاء الاصطناعي

لا تُكتب أي نسبة دقة يدويًا في الكود. تُقاس المقاييس فعليًا على عينات من بيانات عامة، ثم تُستورد إلى لوحة المدير بعد التحقق من اتساقها الحسابي مع مصفوفة الارتباك:

```bash
cd evaluation
../ai-service/.venv/Scripts/python.exe download_samples.py --dataset faces_real_vs_fake --per-class 150
../ai-service/.venv/Scripts/python.exe evaluate.py --dataset faces_real_vs_fake

# استيراد النتائج إلى لوحة المدير
cd ../web
php artisan evaluation:import ../evaluation/results/faces_real_vs_fake.json
```

تفاصيل مجموعات البيانات، ومنهجية التقييم، والنتائج الفعلية المقاسة (ليست 90% ثابتة) في [docs/ACADEMIC.md](docs/ACADEMIC.md).

## التوثيق الإضافي

| المستند | المحتوى |
|---|---|
| [docs/API.md](docs/API.md) | توثيق REST API بين Laravel وFastAPI |
| [docs/ERD.md](docs/ERD.md) | مخطط قاعدة البيانات (12 جدولًا) |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | مخطط بنية النظام |
| [docs/USE_CASE.md](docs/USE_CASE.md) | مخطط حالات الاستخدام لكل دور |
| [docs/SEQUENCE.md](docs/SEQUENCE.md) | تسلسل عملية التحقق الكاملة |
| [docs/ACADEMIC.md](docs/ACADEMIC.md) | الشرح الأكاديمي: البيانات، المنهجية، التقييم، الأخلاقيات، حدود النظام |
| [docs/DEFENSE_POINTS.md](docs/DEFENSE_POINTS.md) | نقاط تساعد على شرح المشروع أمام لجنة المناقشة |
| [docs/sample-reports/](docs/sample-reports/) | نموذج تقرير PDF فعلي |
