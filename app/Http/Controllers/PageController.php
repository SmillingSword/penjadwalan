<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class PageController extends Controller
{
    /**
     * Show the pricing page.
     */
    public function pricing()
    {
        return Inertia::render('Pricing', [
            'title' => 'Pricing - CalendarPro',
            'description' => 'Pilih paket yang sesuai dengan kebutuhan tim Anda'
        ]);
    }

    /**
     * Show the about page.
     */
    public function about()
    {
        return Inertia::render('About', [
            'title' => 'About Us - CalendarPro',
            'description' => 'Tentang CalendarPro dan tim di baliknya'
        ]);
    }

    /**
     * Show the contact page.
     */
    public function contact()
    {
        return Inertia::render('Contact', [
            'title' => 'Contact Us - CalendarPro',
            'description' => 'Hubungi kami untuk pertanyaan atau dukungan'
        ]);
    }

    /**
     * Handle contact form submission.
     */
    public function submitContact(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        // TODO: Implement email sending logic
        // For now, just return success response
        
        return back()->with('success', 'Pesan Anda telah terkirim! Kami akan merespons dalam 24 jam.');
    }
}
