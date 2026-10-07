<?php

return [

    'does_not_exist' => 'Standort existiert nicht.',
    'assoc_users' => 'Dieser Standort kann aktuell nicht gelöscht werden, da ihm mindestens ein Asset, User oder anderer Standort zugewiesen ist. Bitte entferne alle Zuweisungen und versuche es erneut. ',
    'assoc_assets' => 'Dieser Standort ist mindestens einem Gegenstand zugewiesen und kann nicht gelöscht werden. Bitte entferne die Standortzuweisung bei den jeweiligen Gegenständen und versuche erneut, diesen Standort zu entfernen. ',
    'assoc_child_loc' => 'Dieser Standort ist mindestens einem anderen Ort übergeordnet und kann nicht gelöscht werden. Bitte aktualisiere Deine Standorte, so dass dieser Standort nicht mehr verknüpft ist, und versuche es erneut. ',
    'assigned_assets' => 'Zugeordnete Assets',
    'current_location' => 'Aktueller Standort',
    'deleted_warning' => 'Der Standort wurde entfernt. Bitte stellen Sie ihn wieder her, bevor sie versuchen Änderungen vorzunehmen.',

    'create' => [
        'error' => 'Standort wurde nicht erstellt, bitte versuche es erneut.',
        'success' => 'Standort erfolgreich erstellt.',
    ],

    'update' => [
        'error' => 'Standort wurde nicht aktualisiert, bitte versuche es erneut',
        'success' => 'Standort erfolgreich aktualisiert.',
    ],

    'restore' => [
        'error' => 'Der Standort wurde nicht wiederhergestellt. Bitte versuche es erneut',
        'success' => 'Standort erfolgreich wiederhergestellt.',
    ],

    'delete' => [
        'confirm' => 'Bist du sicher, dass du diesen Standort löschen willst?',
        'error' => 'Es gab einen Fehler beim Löschen des Standorts. Bitte erneut versuchen.',
        'success' => 'Der Standort wurde erfolgreich gelöscht.',
    ],

    'bulkedit' => [
        'error' => 'Es wurden keine Felder geändert, somit wurde auch nichts aktualisiert.',
        'success' => 'Location successfully updated.|:count locations successfully updated.',
        'warn' => 'Edit the fields below to update this location. Fields you leave blank will not change on the location.|Edit the fields below to update all :count selected locations. Fields you leave blank will not change on any of them.',
        'show_selected' => '1 selected location|:count selected locations',
        'company_scope_mismatch_partial' => 'The company was not changed on 1 location because items or users at that location belong to different companies. Update or move those first.|The company was not changed on :count locations because items or users at those locations belong to different companies. Update or move those first.',
        'company_scope_mismatch_all' => 'No locations were reassigned. The requested company does not match items or users at the selected location.|No locations were reassigned. The requested company does not match items or users at any of the :count selected locations.',
        'parent_company_mismatch_partial' => 'The parent or company was not changed on 1 location because it would leave the location in a different company than its parent.|The parent or company was not changed on :count locations because it would leave those locations in a different company than their parent.',
        'parent_company_mismatch_all' => 'No changes were saved. The requested parent or company would leave the location in a different company than its parent.|No changes were saved. The requested parent or company would leave every one of the :count selected locations in a different company than their parent.',
    ],

];
