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
        // Pobranie uczestnika
        $participant = Participant::find($participantId);
        if (!$participant) {
            return redirect()->back()->with('error', 'Uczestnik nie został znaleziony.');
        }
  

        // Pobranie kursu powiązanego z uczestnikiem
        $course = Course::find($participant->course_id);
        if (!$course) {
            return redirect()->back()->with('error', 'Szkolenie nie zostało znalezione.');
        }  

        // Generowanie numeru certyfikatu w formacie: NR/course_id/ROK/PNE
        $certificateNumber = sprintf('%d/%d/%s/PNE', $participant->id, $course->id, date('Y'));

        // Sprawdzenie, czy certyfikat już istnieje - jeśli tak, aktualizujemy zamiast dodawać nowy
        $certificate = Certificate::updateOrCreate(
            [
                'participant_id' => $participant->id,
                'course_id' => $course->id,
            ],
            [
                'certificate_number' => $certificateNumber,
                'generated_at' => now(),
            ]
        );

        return redirect()->back()->with('success', 'Certyfikat został zapisany w bazie.');
    }

    public function generate($participantId)
    {
        // Pobieranie uczestnika i kursu
        $participant = Participant::findOrFail($participantId);
        $course = Course::findOrFail($participant->course_id);
    
        // Pobieranie instruktora, jeśli kurs ma relację do instruktora
        $instructor = $course->instructor ?? null;
    
        // Generowanie numeru certyfikatu w formacie NR/course_id/ROK/PNE
        // $certificateNumber = sprintf('%d/%d/%s/PNE', $participant->id, $course->id, date('Y')); 
        // Pobranie liczby certyfikatów już wygenerowanych dla tego kursu
        $lastCertificate = \App\Models\Certificate::where('course_id', $course->id)
            ->orderBy('id', 'desc')
            ->first();

        // Określenie kolejnego numeru certyfikatu dla danego kursu
        $nextCertificateNumber = $lastCertificate ? intval(explode('/', $lastCertificate->certificate_number)[0]) + 1 : 1;

        // Generowanie numeru certyfikatu w formacie: NR/Course_id/YYYY
        $certificateNumber = sprintf('%d/%d/%d/PNE', $nextCertificateNumber, $course->id, date('Y'));


        // Tworzenie nazwy pliku
        $fileName = str_replace('/', '-', $certificateNumber) . '.pdf';
        $filePath = 'certificates/' . $fileName; // Ścieżka w public/storage

        // Sprawdzenie i utworzenie katalogu jeśli nie istnieje
        if (!Storage::disk('public')->exists('certificates')) {
            Storage::disk('public')->makeDirectory('certificates', 0777, true);
        }
       
    
        // Obliczanie czasu trwania szkolenia w minutach
        $startDateTime = Carbon::parse($course->start_date . ' ' . $course->start_time);
        $endDateTime = Carbon::parse($course->end_date . ' ' . $course->end_time);
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
    
        // Zapis w bazie danych (jeśli certyfikat jeszcze nie istnieje)
        Certificate::updateOrCreate(
            [
                'participant_id' => $participant->id,
                'course_id' => $course->id,
            ],
            [
                'certificate_number' => $certificateNumber,
                'file_path' => 'storage/' . $filePath, // Poprawiona ścieżka
                'generated_at' => now(),
            ]
        );
    
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