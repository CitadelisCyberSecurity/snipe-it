<?php

return [

    'does_not_exist' => 'Die Kategorie existiert nicht.',
    'assoc_models' => 'Diese Kategorie kann nicht gelöscht werden, da sie bereits einem Modell zugewiesen ist. Bitte entferne diese Kategorie von Deinen Modellen und versuche es erneut. ',
    'assoc_items' => 'Diese Kategorie kann nicht gelöscht werden, da sie bereits mit einem :asset_type verbunden ist. Bitte trenne diese Kategorie von Deinem :asset_type und versuche es erneut. ',

    'create' => [
        'error' => 'Die Kategorie konnte nicht erstellt werden, bitte versuche es erneut.',
        'success' => 'Die Kategorie wurde erfolgreich erstellt.',
    ],

    'update' => [
        'error' => 'Die Kategorie konnte nicht aktualisiert werden, bitte versuche es erneut',
        'success' => 'Die Kategorie wurde erfolgreich aktualisiert.',
        'cannot_change_category_type' => 'Sobald der Kategorietyp erstellt wurde, kann dieser nicht mehr angepasst werden',
    ],

    'delete' => [
        'confirm' => 'Bist Du sicher, dass du diese Kategorie löschen willst?',
        'error' => 'Beim löschen der Kategorie ist ein Problem aufgetreten. Bitte versuche es erneut.',
        'success' => 'Kategorie erfolgreich gelöscht.',
        'bulk_success' => 'Kategorie erfolgreich gelöscht.|:count Kategorien wurden erfolgreich gelöscht.',
        'partial_success' => 'Kategorie erfolgreich gelöscht. Sehe weitere Informationen weiter unten.',
    ],

    'bulkedit' => [
        'warn' => 'You are about to edit the properties of the following category:|You are about to edit the properties of the following :count categories:',
        'no_selection' => 'You must select at least one category to edit.',
        'no_changes' => 'Es wurden keine Felder geändert, somit wurde auch nichts aktualisiert.',
        'success' => 'Category successfully updated.|:count categories successfully updated.',
    ],

];
