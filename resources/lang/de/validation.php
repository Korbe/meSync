<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Das :attribute-Feld muss akzeptiert werden.',
    'accepted_if' => 'Das :attribute-Feld muss akzeptiert werden, wenn :other :value ist.',
    'active_url' => 'Das :attribute-Feld muss eine gültige URL sein.',
    'after' => 'Das :attribute-Feld muss ein Datum nach :date sein.',
    'after_or_equal' => 'Das :attribute-Feld muss ein Datum nach oder gleich :date sein.',
    'alpha' => 'Das :attribute-Feld darf nur Buchstaben enthalten.',
    'alpha_dash' => 'Das :attribute-Feld darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
    'alpha_num' => 'Das :attribute-Feld darf nur Buchstaben und Zahlen enthalten.',
    'array' => 'Das :attribute-Feld muss ein Array sein.',
    'ascii' => 'Das :attribute-Feld darf nur einbyte-ASCII-Alphanumerika und Symbole enthalten.',
    'before' => 'Das :attribute-Feld muss ein Datum vor :date sein.',
    'before_or_equal' => 'Das :attribute-Feld muss ein Datum vor oder gleich :date sein.',
    'between' => [
        'array' => 'Das :attribute-Feld muss zwischen :min und :max Elemente haben.',
        'file' => 'Das :attribute-Feld muss zwischen :min und :max Kilobyte groß sein.',
        'numeric' => 'Das :attribute-Feld muss zwischen :min und :max liegen.',
        'string' => 'Das :attribute-Feld muss zwischen :min und :max Zeichen enthalten.',
    ],
    'boolean' => 'Das :attribute-Feld muss wahr oder falsch sein.',
    'can' => 'Das :attribute-Feld enthält einen nicht autorisierten Wert.',
    'confirmed' => 'Die Bestätigung des :attribute-Feldes stimmt nicht überein.',
    'contains' => 'Im :attribute-Feld fehlt ein erforderlicher Wert.',
    'current_password' => 'Das Passwort ist falsch.',
    'date' => 'Das :attribute-Feld muss ein gültiges Datum sein.',
    'date_equals' => 'Das :attribute-Feld muss ein Datum gleich :date sein.',
    'date_format' => 'Das :attribute-Feld muss dem Format :format entsprechen.',
    'decimal' => 'Das :attribute-Feld muss :decimal Dezimalstellen haben.',
    'declined' => 'Das :attribute-Feld muss abgelehnt werden.',
    'declined_if' => 'Das :attribute-Feld muss abgelehnt werden, wenn :other :value ist.',
    'different' => 'Das :attribute-Feld und :other müssen unterschiedlich sein.',
    'digits' => 'Das :attribute-Feld muss :digits Ziffern enthalten.',
    'digits_between' => 'Das :attribute-Feld muss zwischen :min und :max Ziffern enthalten.',
    'dimensions' => 'Das :attribute-Feld hat ungültige Bildabmessungen.',
    'distinct' => 'Das :attribute-Feld hat einen doppelten Wert.',
    'doesnt_end_with' => 'Das :attribute-Feld darf nicht mit einem der folgenden Endungen enden: :values.',
    'doesnt_start_with' => 'Das :attribute-Feld darf nicht mit einem der folgenden Anfänge beginnen: :values.',
    'email' => 'Das :attribute-Feld muss eine gültige E-Mail-Adresse sein.',
    'ends_with' => 'Das :attribute-Feld muss mit einem der folgenden Werte enden: :values.',
    'enum' => 'Das ausgewählte :attribute ist ungültig.',
    'exists' => 'Das ausgewählte :attribute ist ungültig.',
    'extensions' => 'Das :attribute-Feld muss eine der folgenden Erweiterungen haben: :values.',
    'file' => 'Das :attribute-Feld muss eine Datei sein.',
    'filled' => 'Das :attribute-Feld muss einen Wert enthalten.',
    'gt' => [
        'array' => 'Das :attribute-Feld muss mehr als :value Elemente haben.',
        'file' => 'Das :attribute-Feld muss größer als :value Kilobyte sein.',
        'numeric' => 'Das :attribute-Feld muss größer als :value sein.',
        'string' => 'Das :attribute-Feld muss mehr als :value Zeichen enthalten.',
    ],
    'gte' => [
        'array' => 'Das :attribute-Feld muss :value oder mehr Elemente haben.',
        'file' => 'Das :attribute-Feld muss größer oder gleich :value Kilobyte sein.',
        'numeric' => 'Das :attribute-Feld muss größer oder gleich :value sein.',
        'string' => 'Das :attribute-Feld muss mindestens :value Zeichen enthalten.',
    ],
    'hex_color' => 'Das :attribute-Feld muss eine gültige hexadezimale Farbe sein.',
    'image' => 'Das :attribute-Feld muss ein Bild sein.',
    'in' => 'Das ausgewählte :attribute ist ungültig.',
    'in_array' => 'Das :attribute-Feld muss in :other existieren.',
    'integer' => 'Das :attribute-Feld muss eine ganze Zahl sein.',
    'ip' => 'Das :attribute-Feld muss eine gültige IP-Adresse sein.',
    'ipv4' => 'Das :attribute-Feld muss eine gültige IPv4-Adresse sein.',
    'ipv6' => 'Das :attribute-Feld muss eine gültige IPv6-Adresse sein.',
    'json' => 'Das :attribute-Feld muss eine gültige JSON-Zeichenkette sein.',
    'list' => 'Das :attribute-Feld muss eine Liste sein.',
    'lowercase' => 'Das :attribute-Feld muss in Kleinbuchstaben sein.',
    'lt' => [
        'array' => 'Das :attribute-Feld muss weniger als :value Elemente haben.',
        'file' => 'Das :attribute-Feld muss kleiner als :value Kilobyte sein.',
        'numeric' => 'Das :attribute-Feld muss kleiner als :value sein.',
        'string' => 'Das :attribute-Feld muss weniger als :value Zeichen enthalten.',
    ],
    'lte' => [
        'array' => 'Das :attribute-Feld darf nicht mehr als :value Elemente haben.',
        'file' => 'Das :attribute-Feld muss kleiner oder gleich :value Kilobyte sein.',
        'numeric' => 'Das :attribute-Feld muss kleiner oder gleich :value sein.',
        'string' => 'Das :attribute-Feld darf höchstens :value Zeichen enthalten.',
    ],
    'mac_address' => 'Das :attribute-Feld muss eine gültige MAC-Adresse sein.',
    'max' => [
        'array' => 'Das :attribute-Feld darf nicht mehr als :max Elemente haben.',
        'file' => 'Das :attribute-Feld darf nicht größer als :max Kilobyte sein.',
        'numeric' => 'Das :attribute-Feld darf nicht größer als :max sein.',
        'string' => 'Das :attribute-Feld darf nicht mehr als :max Zeichen enthalten.',
    ],
    'max_digits' => 'Das :attribute-Feld darf nicht mehr als :max Ziffern haben.',
    'mimes' => 'Das :attribute-Feld muss eine Datei vom Typ: :values sein.',
    'mimetypes' => 'Das :attribute-Feld muss eine Datei vom Typ: :values sein.',
    'min' => [
        'array' => 'Das :attribute-Feld muss mindestens :min Elemente haben.',
        'file' => 'Das :attribute-Feld muss mindestens :min Kilobyte groß sein.',
        'numeric' => 'Das :attribute-Feld muss mindestens :min sein.',
        'string' => 'Das :attribute-Feld muss mindestens :min Zeichen enthalten.',
    ],
    'min_digits' => 'Das :attribute-Feld muss mindestens :min Ziffern haben.',
    'missing' => 'Das :attribute-Feld muss fehlen.',
    'missing_if' => 'Das :attribute-Feld muss fehlen, wenn :other :value ist.',
    'missing_unless' => 'Das :attribute-Feld muss fehlen, sofern :other nicht :value ist.',
    'missing_with' => 'Das :attribute-Feld muss fehlen, wenn :values vorhanden ist.',
    'missing_with_all' => 'Das :attribute-Feld muss fehlen, wenn :values vorhanden sind.',
    'multiple_of' => 'Das :attribute-Feld muss ein Vielfaches von :value sein.',
    'not_in' => 'Das ausgewählte :attribute ist ungültig.',
    'not_regex' => 'Das Format des :attribute-Feldes ist ungültig.',
    'numeric' => 'Das :attribute-Feld muss eine Zahl sein.',
    'password' => [
        'letters' => 'Das :attribute-Feld muss mindestens einen Buchstaben enthalten.',
        'mixed' => 'Das :attribute-Feld muss mindestens einen Groß- und einen Kleinbuchstaben enthalten.',
        'numbers' => 'Das :attribute-Feld muss mindestens eine Zahl enthalten.',
        'symbols' => 'Das :attribute-Feld muss mindestens ein Symbol enthalten.',
        'uncompromised' => 'Das angegebene :attribute wurde in einem Datenleck gefunden. Bitte wählen Sie ein anderes :attribute.',
    ],
    'present' => 'Das :attribute-Feld muss vorhanden sein.',
    'present_if' => 'Das :attribute-Feld muss vorhanden sein, wenn :other :value ist.',
    'present_unless' => 'Das :attribute-Feld muss vorhanden sein, sofern :other nicht :value ist.',
    'present_with' => 'Das :attribute-Feld muss vorhanden sein, wenn :values vorhanden ist.',
    'present_with_all' => 'Das :attribute-Feld muss vorhanden sein, wenn :values vorhanden sind.',
    'prohibited' => 'Das :attribute-Feld ist untersagt.',
    'prohibited_if' => 'Das :attribute-Feld ist untersagt, wenn :other :value ist.',
    'prohibited_unless' => 'Das :attribute-Feld ist untersagt, sofern :other nicht in :values enthalten ist.',
    'prohibits' => 'Das :attribute-Feld verbietet, dass :other vorhanden ist.',
    'regex' => 'Das Format des :attribute-Feldes ist ungültig.',
    'required' => 'Das :attribute-Feld ist erforderlich.',
    'required_array_keys' => 'Das :attribute-Feld muss Einträge für: :values enthalten.',
    'required_if' => 'Das :attribute-Feld ist erforderlich, wenn :other :value ist.',
    'required_if_accepted' => 'Das :attribute-Feld ist erforderlich, wenn :other akzeptiert wurde.',
    'required_if_declined' => 'Das :attribute-Feld ist erforderlich, wenn :other abgelehnt wurde.',
    'required_unless' => 'Das :attribute-Feld ist erforderlich, sofern :other nicht in :values enthalten ist.',
    'required_with' => 'Das :attribute-Feld ist erforderlich, wenn :values vorhanden ist.',
    'required_with_all' => 'Das :attribute-Feld ist erforderlich, wenn :values vorhanden sind.',
    'required_without' => 'Das :attribute-Feld ist erforderlich, wenn :values nicht vorhanden ist.',
    'required_without_all' => 'Das :attribute-Feld ist erforderlich, wenn keines der Felder :values vorhanden ist.',
    'same' => 'Das :attribute-Feld muss mit :other übereinstimmen.',
    'size' => [
        'array' => 'Das :attribute-Feld muss :size Elemente enthalten.',
        'file' => 'Das :attribute-Feld muss :size Kilobyte groß sein.',
        'numeric' => 'Das :attribute-Feld muss :size sein.',
        'string' => 'Das :attribute-Feld muss :size Zeichen lang sein.',
    ],
    'starts_with' => 'Das :attribute-Feld muss mit einem der folgenden Werte beginnen: :values.',
    'string' => 'Das :attribute-Feld muss eine Zeichenkette sein.',
    'timezone' => 'Das :attribute-Feld muss eine gültige Zeitzone sein.',
    'unique' => 'Das :attribute wurde bereits vergeben.',
    'uploaded' => 'Das Hochladen von :attribute ist fehlgeschlagen.',
    'uppercase' => 'Das :attribute-Feld muss in Großbuchstaben sein.',
    'url' => 'Das :attribute-Feld muss eine gültige URL sein.',
    'ulid' => 'Das :attribute-Feld muss eine gültige ULID sein.',
    'uuid' => 'Das :attribute-Feld muss eine gültige UUID sein.',


    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
