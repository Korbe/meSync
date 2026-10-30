<?php

/*
 * Building blocks of an offer for a website or web application: packages, extra options
 * and terms. Prices are net "from" prices and prefill the first item of a new offer.
 */

return [

    'valid_days' => 30,

    'types' => [
        'website' => 'Website (ohne Backend-Logik, Vue.js + Vite) – reine Inhaltsseiten ohne Datenbank/Benutzerverwaltung.',
        'webapp' => 'Web-App (mit Backend-Logik, Laravel + Inertia.js + Vue.js) – individuelle Architektur mit Datenbank, Benutzerverwaltung und dynamischen Funktionen.',
    ],

    'packages' => [
        'one_pager' => [
            'type' => 'website',
            'name' => 'Landing Page',
            'price' => 1000,
            'features' => [
                'Individuell entwickelte einseitige Website mit bis zu 6 Inhaltsbereichen',
                'Modernes UI/UX-Design, responsive auf allen Geräten',
                'Conversionoptimierte Struktur',
                'SEO-Basisoptimierung, Google Analytics & Cookie Banner',
                'Basis Impressum & Datenschutz',
                'Hosting-Unterstützung',
                'Skalierbar für zukünftige Erweiterungen',
            ],
        ],
        'starter' => [
            'type' => 'website',
            'name' => 'Starter Website',
            'price' => 2500,
            // Written out in full: the customer doesn't know what is in the Landing Page
            'features' => [
                'Individuell entwickelte Website mit bis zu 5 Unterseiten',
                'Bis zu 8 Inhaltsbereiche pro Seite',
                'Modernes UI/UX-Design, responsive auf allen Geräten',
                'Conversionoptimierte Struktur',
                'SEO-Basisoptimierung, Google Analytics & Cookie Banner',
                'Basis Impressum & Datenschutz',
                'Hosting-Unterstützung',
                'Skalierbar für zukünftige Erweiterungen',
            ],
        ],
        'webapp' => [
            'type' => 'webapp',
            'name' => 'Web-App-Projekt (mit Backend-Logik)',
            'price' => 3500,
            'features' => [
                'Individuelle Web-Anwendung mit Laravel (Backend), Vue.js und Inertia.js (Frontend)',
                'Konzept, Datenmodell und Datenbank-Design',
                'Benutzerverwaltung mit Login, Rollen und Berechtigungen',
                'Modernes UI/UX-Design, responsive auf allen Geräten',
                'Formulare mit Validierung und E-Mail-Benachrichtigungen',
                'Sicherheit nach aktuellem Stand (HTTPS, CSRF-Schutz, verschlüsselte Passwörter)',
                'Einrichtung von Server, Domain und SSL-Zertifikat, Deployment',
                'Tests und gemeinsame Abnahme vor dem Go-Live',
                'Einschulung in die Bedienung',
            ],
            'description' => 'Aufgrund der andersartigen Architektur (Laravel-Backend, Datenbank, Benutzerverwaltung, individuelle Funktionen, API-Integrationen) wird für Web-Apps kein Festpreis-Paket verrechnet, sondern ein individuelles Angebot erstellt. Richtwert: ab 3.500 €, abhängig vom Funktionsumfang.',
        ],
    ],

    // Dynamic functions and integrations need a backend, so they are only offered for web apps
    'features' => [
        'booking' => 'Buchungssystem',
        'login' => 'Login-/Mitgliederbereich',
        'logic_form' => 'Formular mit Logik',
        'blog' => 'Blog-Modul',
        'funnel' => 'Funnel Kontaktformular',
        'multilingual' => 'Mehrsprachigkeit',
        'contact_backend' => 'Kontaktformular mit Backend-Anbindung',
        'cms' => 'CMS (eigenständige Content-Pflege)',
        'search' => 'Such-/Filterfunktionen',
    ],

    'integrations' => [
        'crm' => 'CRM',
        'newsletter' => 'Newsletter-Tool',
        'calendar' => 'Kalender-/Terminbuchungstool',
        'analytics' => 'Google Analytics',
    ],

    'content_delivery' => [
        'customer' => 'Kunde liefert',
        'provider' => 'Anbieter erstellt',
        'mixed' => 'Gemischt',
    ],

    'payment_terms' => [
        'prepaid' => '100 % bei Auftragserteilung (Vorauskasse)',
        'half' => '50 % bei Auftragserteilung, 50 % bei Fertigstellung',
        'on_completion' => '100 % bei Fertigstellung',
    ],

    'hints' => [
        'features' => 'Blog-Modul, Kontaktformular und weitere dynamische Funktionen sind nur im Rahmen einer Web-App (mit Backend-Logik) realisierbar.',
        'maintenance_start' => 'Der Wartungsvertrag beginnt, sobald die Seite live ist.',
        'terms' => 'Es gelten die allgemeinen Geschäftsbedingungen (AGB) des Anbieters.',
    ],

    // Conditions of offers with terms_version 2 (older offers keep what they went out with)
    'contract' => [
        'maintenance_start' => 'Der Wartungsvertrag beginnt mit der Abnahme, sobald die Seite live ist, bzw. zum vereinbarten Startdatum.',
        'term' => 'Der Wartungsvertrag wird mit einer Mindestvertragslaufzeit von zwölf Monaten abgeschlossen. Nach Ablauf der Mindestvertragslaufzeit verlängert sich der Vertrag automatisch um jeweils weitere zwölf Monate, sofern er nicht unter Einhaltung einer Kündigungsfrist von 30 Tagen vor Ablauf der jeweiligen Vertragslaufzeit schriftlich gekündigt wird.',
        'renewal' => 'Die Wartungsgebühr wird für jede weitere Vertragsperiode von zwölf Monaten erneut im Voraus in Rechnung gestellt.',
        'acceptance' => 'Mit der schriftlichen Annahme dieses Angebots bestätigt der Kunde die Beauftragung der beschriebenen Leistungen sowie die Zustimmung zu den enthaltenen Vertragsbedingungen, insbesondere zur zwölfmonatigen Mindestlaufzeit und zur automatischen Vertragsverlängerung der Wartung.',
    ],

];
