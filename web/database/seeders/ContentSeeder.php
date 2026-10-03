<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/** محتوى الأسئلة الشائعة والمقالات التوعوية الافتراضي. يستطيع المدير تعديله من لوحة التحكم. */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        if (! SystemSetting::where('key', 'faq_items')->exists()) {
            SystemSetting::put('faq_items', $this->faq(), 'json', 'content');
        }

        if (! SystemSetting::where('key', 'awareness_items')->exists()) {
            SystemSetting::put('awareness_items', $this->awareness(), 'json', 'content');
        }
    }

    private function item(string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): array
    {
        return ['title' => ['ar' => $titleAr, 'en' => $titleEn], 'body' => ['ar' => $bodyAr, 'en' => $bodyEn]];
    }

    private function faq(): array
    {
        return [
            $this->item(
                'هل نتيجة المنصة قاطعة؟', 'Is the platform result conclusive?',
                'لا. النتيجة تقدير احتمالي صادر عن نموذج ذكاء اصطناعي، وقد تكون خاطئة. لا تعتمد عليها وحدها في القرارات المهمة، واعرض الحالات الحساسة على خبير في الأدلة الرقمية.',
                'No. The result is a probabilistic estimate from an AI model and may be wrong. Do not rely on it alone for important decisions, and refer sensitive cases to a digital-evidence expert.'
            ),
            $this->item(
                'ما معنى نسبة الثقة؟', 'What does the confidence score mean?',
                'هي تقدير لمدى ثقة النموذج في التصنيف الذي أعطاه. نسبة الثقة المرتفعة لا تعني أن المحتوى حقيقي أو مزيف بيقين، ولا تُعد دليلًا قانونيًا.',
                'It estimates how confident the model is in the classification it gave. A high confidence does not mean the content is certainly real or fake, and it is not legal evidence.'
            ),
            $this->item(
                'ماذا تعني «نتيجة غير حاسمة»؟', 'What does an "inconclusive result" mean?',
                'يعني أن احتمال التلاعب وقع في المنطقة الوسطى بين الحدين، فلا يستطيع النموذج الجزم. في هذه الحالة يُنصح بمراجعة بشرية والتحقق من مصدر المحتوى.',
                'It means the manipulation probability fell in the middle zone between the thresholds, so the model cannot decide. Human review and source verification are recommended.'
            ),
            $this->item(
                'هل تُحفظ ملفاتي؟ ومن يراها؟', 'Are my files stored? Who can see them?',
                'تُحفظ ملفاتك بشكل خاص لمدة محددة ثم تُحذف تلقائيًا. لا يراها إلا أنت وإدارة المنصة عند الضرورة. يمكنك حذفها فورًا في أي وقت من صفحة الإعدادات.',
                'Your files are stored privately for a limited time and then deleted automatically. Only you and the platform administrators (when necessary) can see them. You can delete them immediately from Settings.'
            ),
            $this->item(
                'هل تُستخدم ملفاتي لتدريب النماذج؟', 'Are my files used to train models?',
                'لا. تُستخدم الملفات لتحليلها فقط، ولا تُشارك مع أطراف خارجية.',
                'No. Files are used only for analysis and are not shared with third parties.'
            ),
            $this->item(
                'لماذا فشل التحليل أو استغرق وقتًا طويلًا؟', 'Why did the analysis fail or take a long time?',
                'تحليل الفيديو والصوت يحتاج وقتًا أطول من الصور. قد يفشل إن كانت خدمة التحليل متوقفة مؤقتًا أو كان الملف تالفًا؛ يمكنك إعادة المحاولة من صفحة النتيجة.',
                'Video and audio analysis take longer than images. It may fail if the analysis service is temporarily down or the file is corrupt; you can retry from the result page.'
            ),
            $this->item(
                'كيف أتحقق من صحة تقرير PDF؟', 'How do I verify a PDF report is genuine?',
                'امسح رمز QR الموجود في التقرير أو أدخل الرقم المرجعي في صفحة «التحقق من تقرير» لتظهر بيانات التقرير الأساسية.',
                'Scan the QR code in the report or enter the reference number on the "Verify a report" page to see the report basic details.'
            ),
        ];
    }

    private function awareness(): array
    {
        return [
            $this->item(
                'ما هو التزييف العميق (Deepfake)؟', 'What is a deepfake?',
                "التزييف العميق تقنية تستخدم التعلّم العميق لتوليد صور أو مقاطع فيديو أو أصوات تبدو حقيقية، مثل تبديل وجه شخص بوجه آخر أو استنساخ صوته.\n\nقد تُستخدم لأغراض مشروعة كالترفيه والسينما، لكنها تُستخدم أيضًا في الاحتيال ونشر الأخبار المضللة والإساءة إلى الأشخاص.",
                "A deepfake uses deep learning to generate images, videos or voices that look real, such as swapping one person's face for another's or cloning their voice.\n\nIt can serve legitimate purposes like entertainment and film, but is also used for fraud, misinformation and harming people."
            ),
            $this->item(
                'علامات قد تدل على محتوى مزيف', 'Signs that may indicate fake content',
                "ملامح وجه غير متسقة، أو إضاءة وظلال لا تتناسب مع المشهد، أو تشوهات حول العينين والأسنان والشعر والأذنين، أو حدود غير طبيعية حول الوجه، أو حركة شفاه لا تتطابق مع الصوت، أو صوت رتيب بتوقفات غير طبيعية.\n\nغياب هذه العلامات لا يعني أن المحتوى حقيقي، فالتقنيات الحديثة تتقن إخفاءها.",
                "Inconsistent facial features, lighting and shadows that do not match the scene, distortions around the eyes, teeth, hair and ears, unnatural edges around the face, lip movement that does not match the audio, or a flat voice with unnatural pauses.\n\nThe absence of these signs does not mean the content is real, as modern techniques hide them well."
            ),
            $this->item(
                'كيف تتحقق قبل أن تشارك؟', 'How to verify before you share',
                "ابحث عن المصدر الأصلي، وانظر من نشر المحتوى أولًا. قارن الخبر بمصادر موثوقة، وابحث عن الصورة بالبحث العكسي. لا تنشر محتوى صادمًا أو مثيرًا قبل التأكد منه.\n\nيمكنك استخدام هذه المنصة كأداة مساعدة، مع مراعاة حدود دقتها.",
                "Find the original source and who published the content first. Compare the story with trusted sources and use reverse image search. Do not share shocking or sensational content before verifying it.\n\nYou can use this platform as an assistive tool, keeping its accuracy limits in mind."
            ),
            $this->item(
                'حماية نفسك من الاحتيال بالأصوات المستنسخة', 'Protecting yourself from cloned-voice scams',
                'قد يتصل بك شخص بصوت يشبه صوت قريب أو مدير طالبًا مالًا أو معلومات. اتفق مع أسرتك وزملائك على كلمة سرية، وأعد الاتصال على الرقم المعروف قبل أي تحويل مالي أو مشاركة بيانات حساسة.',
                'Someone may call with a voice that sounds like a relative or manager asking for money or information. Agree on a secret word with family and colleagues, and call back on the known number before any transfer or sharing sensitive data.'
            ),
            $this->item(
                'حدود أدوات الكشف الآلي', 'Limits of automated detection tools',
                'أدوات الكشف الآلي، ومنها هذه المنصة، تخطئ. قد تصف محتوى حقيقيًا بأنه مزيف أو العكس، وتقل دقتها مع الأساليب الجديدة والملفات المضغوطة. اعتبرها مؤشرًا مساعدًا لا حكمًا نهائيًا.',
                'Automated detection tools, including this platform, make mistakes. They may call real content fake or the reverse, and lose accuracy with new methods and compressed files. Treat them as a supporting indicator, not a final verdict.'
            ),
        ];
    }
}
