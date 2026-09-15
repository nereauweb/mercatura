<?php

declare(strict_types=1);

/*
| Transactional mail copy. Overridable per skin from resources/skins/<name>/lang/it/mail.php.
*/

return [
    'greeting' => 'Gentile cliente,',
    'signature' => 'Il team di :brand',
    'footer_contacts' => 'Contatti',

    'labels' => [
        'company' => 'Azienda',
        'name' => 'Nome',
        'surname' => 'Cognome',
        'email' => 'Email',
        'phone' => 'Telefono',
        'activity' => 'Attività',
        'customer_type' => 'Tipo cliente',
        'subject' => 'Oggetto',
        'message' => 'Messaggio',
        'consent_gdpr' => 'Consenso privacy',
        'consent_terms' => 'Accettazione condizioni',
        'subscribe_newsletter' => 'Iscrizione newsletter',
        'tax_code' => 'Codice fiscale',
        'vat_code' => 'Partita IVA',
        'sdi_code' => 'Codice SDI',
        'ipa_code' => 'Codice IPA',
        'cig_code' => 'Codice CIG',
        'pec' => 'PEC',
        'quantity' => 'Quantità',
        'printing' => 'Personalizzazione',
        'color' => 'Colore',
        'size' => 'Taglia',
        'notes' => 'Note',
    ],

    'order' => [
        'title' => 'Ordine n. :id',
        'customer_title' => 'Dati cliente',
        'items_title' => 'Articoli',
        'date' => 'Data',
        'status' => 'Stato',
        'payment_status' => 'Pagamento',
        'tracking_code' => 'Tracking spedizione',
        'address' => 'Indirizzo di spedizione',
        'notes' => 'Note',
        'items_price' => 'Totale articoli',
        'delivery_cost' => 'Spese di spedizione',
        'total_price' => 'Imponibile',
        'total_tax' => 'IVA',
        'total_taxed_price' => 'Totale',
        'cta' => 'Vedi i tuoi ordini',
    ],

    'quotation' => [
        'items_title' => 'Articoli richiesti',
    ],

    'welcome' => [
        'subject' => 'Benvenuto su :brand',
        'title' => 'Benvenuto su :brand',
        'greeting' => 'Ciao :name,',
        'intro' => 'il tuo account su :brand è attivo. Da oggi puoi salvare i tuoi dati, richiedere preventivi e seguire lo stato dei tuoi ordini.',
        'cta' => 'Vai al tuo profilo',
    ],
    'password_reset' => [
        'subject' => 'Reimposta la tua password',
        'title' => 'Reimposta la tua password',
        'intro' => 'Abbiamo ricevuto una richiesta di reimpostazione della password per il tuo account su :brand. Clicca il pulsante per sceglierne una nuova.',
        'cta' => 'Reimposta la password',
        'ignore' => 'Se non hai richiesto tu la reimpostazione puoi ignorare questa email: la password attuale resta valida.',
    ],
    'contact_admin' => [
        'subject' => 'Nuovo messaggio dal modulo contatti',
        'title' => 'Nuovo messaggio dal modulo contatti',
        'intro' => 'È arrivato un nuovo messaggio dal sito :brand.',
    ],
    'register_admin' => [
        'subject' => 'Nuova registrazione cliente',
        'title' => 'Nuova registrazione cliente',
        'intro' => 'Un nuovo cliente si è registrato su :brand.',
    ],
    'quotation_customer' => [
        'subject' => 'Abbiamo ricevuto la tua richiesta di preventivo',
        'title' => 'Richiesta di preventivo ricevuta',
        'greeting' => 'Ciao :name,',
        'intro' => 'grazie per la tua richiesta del :date su :brand. La stiamo valutando e ti risponderemo al più presto con un\'offerta.',
        'outro' => 'Per qualsiasi domanda',
        'contacts' => 'contattaci',
    ],
    'quotation_admin' => [
        'subject' => 'Nuova richiesta di preventivo',
        'title' => 'Nuova richiesta di preventivo',
        'intro' => 'È arrivata una nuova richiesta di preventivo (:date) dal sito :brand.',
    ],
    'order_stored_user' => [
        'subject' => 'Ordine n. :order_id ricevuto',
        'title' => 'Ordine n. :id ricevuto',
        'greeting' => 'Ciao :name,',
        'intro' => 'grazie per il tuo ordine su :brand. Lo abbiamo registrato e ti aggiorneremo a ogni cambio di stato.',
    ],
    'order_stored_admin' => [
        'subject' => 'Nuovo ordine n. :order_id',
        'title' => 'Nuovo ordine n. :id',
        'intro' => 'È arrivato un nuovo ordine su :brand.',
    ],
    'order_paid_user' => [
        'subject' => 'Pagamento ricevuto per l\'ordine n. :order_id',
        'title' => 'Pagamento ricevuto',
        'greeting' => 'Ciao :name,',
        'intro' => 'abbiamo ricevuto il pagamento dell\'ordine n. :id. Ora lo prepariamo per la spedizione.',
    ],
    'order_paid_admin' => [
        'subject' => 'Pagamento ricevuto per l\'ordine n. :order_id',
        'title' => 'Pagamento ricevuto',
        'intro' => 'Il pagamento dell\'ordine n. :id su :brand è stato confermato.',
    ],
    'order_updated' => [
        'subject' => 'Aggiornamento ordine n. :order_id',
        'title' => 'Ordine n. :id aggiornato',
        'greeting' => 'Ciao :name,',
        'intro' => 'il tuo ordine n. :id è ora nello stato ":status".',
    ],
    'order_sent' => [
        'subject' => 'Ordine n. :order_id spedito',
        'title' => 'Ordine n. :id spedito',
        'greeting' => 'Ciao :name,',
        'intro' => 'il tuo ordine n. :id è stato spedito. Codice di tracciamento: :tracking',
    ],
    'order_cancelled' => [
        'subject' => 'Ordine n. :order_id annullato',
        'title' => 'Ordine n. :id annullato',
        'greeting' => 'Ciao :name,',
        'intro' => 'il tuo ordine n. :id è stato annullato. Se non te lo aspettavi, contattaci.',
    ],
    'request_quotation' => [
        'subject' => 'Nuova richiesta di contatto',
        'title' => 'Nuova richiesta di contatto',
        'intro' => 'È arrivata una nuova richiesta dal sito :brand.',
        'company' => 'Azienda',
        'name' => 'Nome',
        'surname' => 'Cognome',
        'email' => 'Email',
        'phone' => 'Telefono',
        'activity' => 'Attività',
        'subject_field' => 'Oggetto',
        'message' => 'Messaggio',
    ],
];
