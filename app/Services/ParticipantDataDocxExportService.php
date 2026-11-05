<?php

namespace App\Services;

use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class ParticipantDataDocxExportService
{
    private const EMPTY_ROWS_FOR_MANUAL_COMPLETION = 10;

    public function generate(Course $course, Collection $participants): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Rozszerzenie PHP ZipArchive jest wymagane do wygenerowania pliku DOCX.');
        }

        $path = tempnam(sys_get_temp_dir(), 'participants-docx-');
        if ($path === false) {
            throw new RuntimeException('Nie można utworzyć pliku tymczasowego dla eksportu DOCX.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Nie można przygotować archiwum DOCX.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('word/document.xml', $this->documentXml($course, $participants));
        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        if ($content === false) {
            throw new RuntimeException('Nie można odczytać wygenerowanego pliku DOCX.');
        }

        return $content;
    }

    public function complianceNote(): string
    {
        return 'Adres e-mail nie jest obowiązkowy. Jego podanie umożliwia uczestnikowi bezpośredni dostęp do platformy z nagraniem szkolenia, materiałami oraz zaświadczeniem. Dane osobowe uczestników są przetwarzane przez organizatora szkolenia w celu organizacji i udokumentowania udziału w szkoleniu, prowadzenia listy uczestników oraz rejestru wydanych zaświadczeń, zgodnie z przepisami prawa oświatowego dotyczącymi placówek doskonalenia nauczycieli i dokumentacji kształcenia oraz z obowiązkami wynikającymi z RODO. Adresy e-mail nie są wykorzystywane do celów marketingowych bez odrębnej, dobrowolnej zgody uczestnika.';
    }

    private function documentXml(Course $course, Collection $participants): string
    {
        $rows = $participants->map(function ($participant): string {
            return $this->tableRow([
                $participant->first_name,
                $participant->last_name,
                $participant->birth_date?->format('d.m.Y') ?? '',
                $participant->birth_place,
                $participant->email,
            ]);
        })->implode('');
        $emptyRows = str_repeat($this->tableRow(['', '', '', '', '']), self::EMPTY_ROWS_FOR_MANUAL_COMPLETION);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'
            .$this->paragraph('Lista uczestników szkolenia', ['bold' => true, 'size' => 32, 'align' => 'center'])
            .$this->paragraph('Tytuł szkolenia: '.$this->plainText($course->title), ['bold' => true, 'size' => 24])
            .$this->paragraph('Data: '.$this->formatCourseDate($course), ['size' => 22])
            .$this->paragraph('Prowadzący: '.$this->instructorName($course), ['size' => 22])
            .$this->paragraph('Liczba uczestników: '.$participants->count(), ['size' => 22])
            .$this->paragraph('', ['size' => 10])
            .$this->table(
                $this->tableRow(['Imię', 'Nazwisko', 'Data urodzenia', 'Miejsce urodzenia', 'Adres e-mail'], true)
                .$rows
                .$emptyRows
            )
            .$this->paragraph('', ['size' => 10])
            .$this->paragraph($this->complianceNote(), ['size' => 18])
            .'<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>';
    }

    private function table(string $rows): string
    {
        return '<w:tbl>'
            .'<w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
            .'<w:top w:val="single" w:sz="6" w:space="0" w:color="000000"/>'
            .'<w:left w:val="single" w:sz="6" w:space="0" w:color="000000"/>'
            .'<w:bottom w:val="single" w:sz="6" w:space="0" w:color="000000"/>'
            .'<w:right w:val="single" w:sz="6" w:space="0" w:color="000000"/>'
            .'<w:insideH w:val="single" w:sz="6" w:space="0" w:color="000000"/>'
            .'<w:insideV w:val="single" w:sz="6" w:space="0" w:color="000000"/>'
            .'</w:tblBorders></w:tblPr>'
            .$rows
            .'</w:tbl>';
    }

    private function tableRow(array $cells, bool $header = false): string
    {
        $cellXml = collect($cells)->map(fn ($cell): string => $this->tableCell((string) ($cell ?? ''), $header))->implode('');

        return '<w:tr>'.$cellXml.'</w:tr>';
    }

    private function tableCell(string $text, bool $header = false): string
    {
        return '<w:tc><w:tcPr><w:tcW w:w="2400" w:type="dxa"/></w:tcPr>'
            .$this->paragraph($text, ['bold' => $header, 'size' => 18])
            .'</w:tc>';
    }

    private function paragraph(string $text, array $options = []): string
    {
        $align = isset($options['align']) ? '<w:jc w:val="'.$this->xml($options['align']).'"/>' : '';
        $bold = ! empty($options['bold']) ? '<w:b/>' : '';
        $size = isset($options['size']) ? '<w:sz w:val="'.(int) $options['size'].'"/><w:szCs w:val="'.(int) $options['size'].'"/>' : '';

        return '<w:p><w:pPr>'.$align.'</w:pPr><w:r><w:rPr>'.$bold.$size.'</w:rPr><w:t xml:space="preserve">'.$this->xml($text).'</w:t></w:r></w:p>';
    }

    private function formatCourseDate(Course $course): string
    {
        if (! $course->start_date) {
            return '';
        }

        $start = Carbon::parse($course->start_date);
        $end = $course->end_date ? Carbon::parse($course->end_date) : null;

        if (! $end) {
            return $start->format('d.m.Y H:i');
        }

        if ($start->isSameDay($end)) {
            return $start->format('d.m.Y').' godz. '.$start->format('H:i').'–'.$end->format('H:i');
        }

        return $start->format('d.m.Y H:i').' – '.$end->format('d.m.Y H:i');
    }

    private function instructorName(Course $course): string
    {
        $instructor = $course->instructor;

        return $instructor?->full_title_name ?: '';
    }

    private function plainText(?string $value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function xml(string $value): string
    {
        $value = preg_replace('/[^\P{C}\t\n\r]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }
}
