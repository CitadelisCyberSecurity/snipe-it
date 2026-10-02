<?php

return [

    'does_not_exist' => 'Standort nicht verfügbar.',
    'assoc_users' => 'Dieser Standort kann aktuell nicht gelöscht werden, da ihm mindestens ein Asset, User oder anderer Standort zugewiesen ist. Bitte entfernen Sie alle Zuweisungen und versuchen Sie es erneut. ',
    'assoc_assets' => 'Dieser Standort ist aktuell mindestens einem Gegenstand zugewiesen und kann nicht gelöscht werden. Bitte entfernen Sie die Standortzuweisung bei den jeweiligen Gegenständen und versuchen Sie es erneut diesen Standort zu entfernen. ',
    'assoc_child_loc' => 'Dieser Ort ist aktuell mindestens einem anderen Ort übergeordnet und kann nicht gelöscht werden. Bitte Orte aktualisieren, so dass dieser Standort nicht mehr verknüpft ist und erneut versuchen. ',
    'assigned_assets' => 'Zugeordnete Assets',
    'current_location' => 'Aktueller Standort',
    'deleted_warning' => 'Dieser Standort wurde gelöscht. Bitte stellen Sie ihn wieder her, bevor Sie Änderungen vornehmen.',

    'create' => [
        'error' => 'Standort wurde nicht erstellt, bitte versuchen Sie es erneut.',
        'success' => 'Standort erfolgreich erstellt.',
    ],

    'update' => [
        'error' => 'Standort wurde nicht aktualisiert, bitte erneut versuchen',
        'success' => 'Standort erfolgreich aktualisiert.',
    ],

    'restore' => [
        'error' => 'Der Standort wurde nicht wiederhergestellt. Bitte versuchen Sie es erneut',
        'success' => 'Standort erfolgreich wiederhergestellt.',
    ],

    'delete' => [
        'confirm' => 'Möchten Sie diesen Standort wirklich entfernen?',
        'error' => 'Es gab einen Fehler beim Löschen des Standorts. Bitte erneut versuchen.',
        'success' => 'Der Standort wurde erfolgreich gelöscht.',
    ],

    'bulkedit' => [
        'error' => 'Es wurden keine Felder geändert, daher wurde nichts aktualisiert.',
        'success' => 'Location successfully updated.|:count locations successfully updated.',
        'warn' => 'Edit the fields below to update this location. Fields you leave blank will not change on the location.|Edit the fields below to update all :count selected locations. Fields you leave blank will not change on any of them.',
        'show_selected' => '1 selected location|:count selected locations',
        'company_scope_mismatch_partial' => 'The company was not changed on 1 location because items or users at that location belong to different companies. Update or move those first.|The company was not changed on :count locations because items or users at those locations belong to different companies. Update or move those first.',
        'company_scope_mismatch_all' => 'No locations were reassigned. The requested company does not match items or users at the selected location.|No locations were reassigned. The requested company does not match items or users at any of the :count selected locations.',
        'parent_company_mismatch_partial' => 'The parent or company was not changed on 1 location because it would leave the location in a different company than its parent.|The parent or company was not changed on :count locations because it would leave those locations in a different company than their parent.',
        'parent_company_mismatch_all' => 'No changes were saved. The requested parent or company would leave the location in a different company than its parent.|No changes were saved. The requested parent or company would leave every one of the :count selected locations in a different company than their parent.',
    ],

];
