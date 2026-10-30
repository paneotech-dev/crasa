<?php

/*
 * Jour 3, parcours du rédacteur (point 1a).
 */

return [
    'save_draft' => 'Enregistrer le brouillon',
    'save_send' => 'Enregistrer et envoyer pour approbation',
    'note_label' => 'Note pour le modérateur (facultatif)',
    'note_help' => 'Ce qui a changé et ce que le modérateur doit vérifier. Envoyée uniquement avec Enregistrer et envoyer pour approbation.',
    'actions_help' => 'Enregistrer le brouillon conserve votre travail sans l\'envoyer. Enregistrer et envoyer pour approbation l\'enregistre et demande à un modérateur de le publier.',

    'saved_draft' => 'Brouillon enregistré. Il n\'est pas sur le site public. Lorsqu\'il est prêt, choisissez Enregistrer et envoyer pour approbation.',
    'sent' => 'Envoyé pour approbation. Un modérateur l\'approuvera ou le renverra avec un commentaire, et vous recevrez une notification dans les deux cas. Il ne peut pas être modifié d\'ici là. Une fois approuvé, il est sur le site public.',
    'saved_revision' => 'Vos modifications sont enregistrées comme brouillon. La version publiée reste en ligne. Lorsqu\'elles sont prêtes, choisissez Enregistrer et envoyer pour approbation.',
    'sent_revision' => 'Vos modifications attendent une approbation ; la version publiée reste en ligne jusqu\'à ce qu\'un modérateur les approuve.',

    'revision_draft' => 'Vous modifiez un brouillon de modifications d\'un contenu publié. Le site public affiche la version publiée jusqu\'à ce qu\'un modérateur approuve vos modifications.',
    'revision_waiting' => 'Vos modifications attendent une approbation ; la version publiée reste en ligne jusqu\'à ce qu\'un modérateur les approuve.',
    'revision_returned' => 'Un modérateur a renvoyé vos modifications. La version publiée est toujours en ligne. Modifiez-les et envoyez-les à nouveau.',
    'revision_label' => 'Modifications de la version publiée',

    'inbox_title' => 'En attente de votre approbation',
    'inbox_body' => '{1} :count contenu attend votre décision.|[2,*] :count contenus attendent votre décision.',
    'inbox_open' => 'Ouvrir la boîte des approbations',

    'event_public_help' => 'Les événements que vous ajoutez sont d\'abord réservés aux membres. Un modérateur les met sur le site public en les approuvant.',
    'event_public_kept' => 'Cet événement est sur le site public et le reste. Vos modifications sont mises en ligne lorsqu\'un modérateur les approuve.',
    'event_files_held' => 'Les documents d\'un événement public ne peuvent pas être modifiés dans une modification en attente d\'approbation. Enregistrez d\'abord les autres modifications, puis demandez à un modérateur d\'ajouter ou de retirer les documents.',
    'status_refused' => 'Les rédacteurs ne choisissent pas de statut. Utilisez Enregistrer le brouillon ou Enregistrer et envoyer pour approbation.',
    'moderator_revision' => 'Il s\'agit de modifications d\'un contenu publié. La version publiée reste en ligne jusqu\'à votre approbation ; approuver met les modifications sur le site public. Le tableau les compare à la version publiée.',
    'page_status_help' => 'Un modérateur publie cette page après que vous l\'avez envoyée pour approbation.',
];
