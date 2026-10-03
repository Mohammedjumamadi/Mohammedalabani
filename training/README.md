# تدريب نماذج الكشف (Fine-tuning)

هذا المجلد يضبط نماذج الإنتاج الحالية (لا تدريب من الصفر) على بيانات عامة إضافية، ثم يقارنها بالنماذج الأصلية
على بيانات التقييم في `evaluation/data` التي **لا تدخل في التدريب إطلاقًا**. لا يُنقل نموذج إلى الإنتاج
(`ai-service/models/<key>/`) إلا إذا تفوّق فعليًا على النموذج الأصلي.

## البيئة

بيئة مستقلة عن خدمة الإنتاج (حتى لا يتوقف الموقع أثناء التدريب)، مع PyTorch يدعم كرت NVIDIA:

```bash
python -m venv training/.venv
training/.venv/Scripts/python.exe -m pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu130
training/.venv/Scripts/python.exe -m pip install -r training/requirements.txt
```

## الخطوات

```bash
cd training
.venv/Scripts/python.exe prepare_data.py --task all            # تنزيل البيانات وتجهيزها مع منع التسرّب
.venv/Scripts/python.exe train_image.py --task ai_images --base umm-maybe/AI-image-detector --fake-labels artificial --out models/image_gen
.venv/Scripts/python.exe train_image.py --task faces --base prithivMLmods/Deep-Fake-Detector-v2-Model --fake-labels deepfake --out models/image_face
.venv/Scripts/python.exe train_audio.py --base mo-thecreator/Deepfake-audio-detection --fake-labels fake --out models/audio_voice
.venv/Scripts/python.exe compare.py                            # مقارنة الأصلي بالمضبوط على بيانات التقييم
```

## مصادر البيانات وتراخيصها

| المهمة | المصدر | الترخيص |
|---|---|---|
| صور مولّدة/حقيقية | [Hemg/AI-Generated-vs-Real-Images-Datasets](https://huggingface.co/datasets/Hemg/AI-Generated-vs-Real-Images-Datasets) | غير محدد في المستودع (استخدام بحثي) |
| وجوه مزيفة/حقيقية | [OpenRL/DeepFakeFace](https://huggingface.co/datasets/OpenRL/DeepFakeFace) | OpenRAIL |
| أصوات عربية مزيفة/حقيقية | [DeepFake-Audio-Rangers/Arabic_Audio_Deepfake](https://huggingface.co/datasets/DeepFake-Audio-Rangers/Arabic_Audio_Deepfake) | ODC-By |
| أصوات إنجليزية | [garystafford/deepfake-audio-detection](https://huggingface.co/datasets/garystafford/deepfake-audio-detection) | CC BY 4.0 |

## منع التسرّب (سبب الثقة في الأرقام)

1. صور التقييم المأخوذة من Hemg تُستبعد من التدريب ببصمة SHA-256 للبايتات وبصمة بكسلات (aHash).
2. مقاطع garystafford مقطوعة من تسجيلات طويلة؛ يُستبعد **التسجيل كاملًا** إن ظهر أي جزء منه في التقييم.
3. نموذج الوجوه يُدرَّب على DeepFakeFace ويُقاس على Sowaiba01/Deepfake (مصدر مختلف تمامًا): قياس تعميم حقيقي.
4. للصوت العربي قسم اختبار رسمي مستقل (`test_arad`) لا يُستخدم في التدريب ولا في اختيار أفضل نسخة.

## العتاد المستخدم

NVIDIA T600 Laptop (4 GB) مع fp16 وتجميع التدرجات (gradient accumulation) وحفظ الذاكرة (gradient checkpointing).
