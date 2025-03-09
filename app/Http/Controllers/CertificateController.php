<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Certificate;
use App\Models\Participant;
use App\Models\Course;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CertificateController extends Controller
{


    public function store($participantId)
    {
        // Pobieranie uczestnika i kursu
        $participant = Participant::findOrFail($participantId);
        $course = Course::findOrFail($participant->course_id);
    
        // Pobranie ostatniego certyfikatu dla danego kursu
        $lastCertificate = Certificate::where('course_id', $course->id)
            ->orderBy('id', 'desc')
            ->first();
    
        // Określenie kolejnego numeru certyfikatu dla kursu
        $nextCertificateNumber = $lastCertificate 
            ? intval(explode('/', $lastCertificate->certificate_number)[0]) + 1 
            : 1;
    
        // Pobranie schematu numeracji lub użycie domyślnego
        $format = $course->certificate_format ?? "{nr}/{course_id}/{year}";
    
        // Generowanie numeru certyfikatu według wzoru
        $certificateNumber = str_replace(
            ['{nr}', '{year}', '{course_id}'], 
            [$nextCertificateNumber, date('Y'), $course->id], 
            $format
        );
    
        // Zapis numeru certyfikatu w bazie danych (bez generowania PDF)
        Certificate::updateOrCreate(
            ['participant_id' => $participant->id, 'course_id' => $course->id],
            ['certificate_number' => $certificateNumber, 'generated_at' => now()]
        );
    
        return redirect()->back()->with('success', "Certyfikat nr {$certificateNumber} został zapisany.");
    }
    
    

    public function generate($participantId)
    {
        // Pobieranie uczestnika i kursu
        $participant = Participant::findOrFail($participantId);
        $course = Course::findOrFail($participant->course_id);
    
        // Pobieranie instruktora, jeśli kurs ma relację do instruktora
        $instructor = $course->instructor ?? null;
    
        // Pobranie istniejącego certyfikatu z bazy danych
        $certificate = Certificate::where('participant_id', $participant->id)
            ->where('course_id', $course->id)
            ->first();
    
        // Jeśli certyfikat nie istnieje, zwracamy błąd
        if (!$certificate) {
            return redirect()->back()->with('error', 'Certyfikat dla tego uczestnika nie został znaleziony.');
        }
    
        // Używamy istniejącego numeru certyfikatu
        $certificateNumber = $certificate->certificate_number;
    
        // Tworzenie nazwy pliku na podstawie numeru certyfikatu
        $fileName = str_replace('/', '-', $certificateNumber) . '.pdf';
        $filePath = 'certificates/' . $fileName; // Ścieżka w public/storage
    
        // Tworzenie ścieżki do folderu kursu
        $courseFolder = 'certificates/' . $course->id;
        $fileName = str_replace('/', '-', $certificateNumber) . '.pdf';
        $filePath = $courseFolder . '/' . $fileName; // Ścieżka w public/storage

        // Sprawdzenie i utworzenie katalogu jeśli nie istnieje
        if (!Storage::disk('public')->exists($courseFolder)) {
            Storage::disk('public')->makeDirectory($courseFolder, 0777, true);
        }
    
        // Usuwamy ewentualne spacje i konwertujemy format
        $startDateTime = Carbon::parse(trim($course->start_date));
        $endDateTime = Carbon::parse(trim($course->end_date));

        // Obliczanie czasu trwania w minutach
        $durationMinutes = $startDateTime->diffInMinutes($endDateTime);


    
        // Tworzenie widoku PDF z przekazaniem wszystkich danych
        $pdf = Pdf::loadView('certificates.template', [
            'participant' => $participant,
            'certificateNumber' => $certificateNumber,
            'course' => $course,
            'instructor' => $instructor,
            'durationMinutes' => $durationMinutes,
        ])->setPaper('A4', 'portrait')
          ->setOptions([
              'defaultFont' => 'DejaVu Sans', // Obsługa polskich znaków
              'isHtml5ParserEnabled' => true, 
              'isRemoteEnabled' => true
          ]);
    
        // Zapisywanie pliku PDF w storage/public/certificates
        Storage::disk('public')->put($filePath, $pdf->output());
    
        // Aktualizacja ścieżki pliku w bazie (jeśli brak)
        if (empty($certificate->file_path)) {
            $certificate->update([
                'file_path' => 'storage/' . $filePath,
                'generated_at' => now(),
            ]);
        }
    
        // Pobieranie pliku PDF (z folderu public/storage)
        return response()->download(storage_path('app/public/' . $filePath));
    }
    

    public function destroy(Certificate $certificate)
    {
        //dd($certificate->file_path);

        // Sprawdzenie, czy certyfikat ma powiązany plik PDF
        if ($certificate->file_path) {
            $filePath = storage_path("app/public/" . str_replace("storage/", "", $certificate->file_path));
    
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    
        // Usunięcie certyfikatu z bazy danych
        $certificate->delete();
    
        return redirect()->back()->with('success', 'Certyfikat został usunięty.');
    }
    
    

}