<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDF; // Ensure the facade is imported
use Omaralalwi\Gpdf\Facade\Gpdf as GpdfFacade;

class PDFController extends Controller
{
    public function generatePDF()
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
        ]; // Replace with your actual data

        // Render the view to HTML
        $html = view('your-view', $data)->render();

        // Use Gpdf (which wraps DomPDF and includes Arabic/RTL fixes) to generate the PDF
        $pdfContent = GpdfFacade::generate($html);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="document.pdf"',
        ]);
    }

    public function generateArabicPdf()
    {
        $data = [
            'key1' => 'قيمة 1',
            'key2' => 'قيمة 2',
        ]; // Replace with your actual data

        // Render your Blade view containing Arabic text
        $html = view('pdf.invoice', $data)->render();

        // Generate the PDF content
        $pdfContent = GpdfFacade::generate($html);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="arabic_report.pdf"',
        ]);
    }
}