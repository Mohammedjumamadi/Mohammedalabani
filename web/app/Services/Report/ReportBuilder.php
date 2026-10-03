<?php

namespace App\Services\Report;

use App\Models\VerificationRequest;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class ReportBuilder
{
    /** ينشئ تقرير PDF احترافيًا بلغة الواجهة الحالية، ويعيده كمحتوى ثنائي. */
    public function pdf(VerificationRequest $verification): string
    {
        $verification->loadMissing(['mediaFile', 'result.indicators', 'result.modelVersion']);

        $rtl = app()->getLocale() === 'ar';
        $verifyUrl = route('reports.verify', $verification->reference_number);

        $html = view('reports.pdf', [
            'verification' => $verification,
            'result' => $verification->result,
            'media' => $verification->mediaFile,
            'indicators' => $verification->result->indicators->sortByDesc('score')->values(),
            'verifyUrl' => $verifyUrl,
            'qr' => $this->qrDataUri($verifyUrl),
            'rtl' => $rtl,
        ])->render();

        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'default_font' => $rtl ? 'xbriyaz' : 'dejavusans',
            'default_font_size' => 11,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 14,
            'margin_bottom' => 18,
        ]);
        $mpdf->SetDirectionality($rtl ? 'rtl' : 'ltr');
        $mpdf->SetTitle(config('app.name').' - '.$verification->reference_number);
        $mpdf->SetAuthor(config('app.name'));
        $mpdf->SetHTMLFooter(
            '<table width="100%" style="font-size:8pt;color:#555;"><tr>'
            .'<td>'.e($verification->reference_number).'</td>'
            .'<td align="center">'.e(config('app.name')).'</td>'
            .'<td align="'.($rtl ? 'left' : 'right').'"><span dir="ltr">{PAGENO} / {nbpg}</span></td></tr></table>'
        );
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /** رمز QR بصيغة SVG مضمّن كـ data URI (لا يحتاج إلى امتداد Imagick). */
    public function qrDataUri(string $url): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($url));
    }
}
