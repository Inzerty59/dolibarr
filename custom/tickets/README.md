# Module Tickets Personnalisé pour Dolibarr

## Description
Ce module personnalise le flux de création de tickets dans Dolibarr. Au lieu de créer directement un ticket, l'utilisateur doit d'abord sélectionner un projet auquel il est assigné.

## Fonctionnalités
- Affiche une liste des projets actifs assignés à l'utilisateur
- Oblige à sélectionner un projet avant création de ticket
- Le ticket est automatiquement lié au projet sélectionné
- Affiche uniquement les projets assignés à l'utilisateur (pas les autres)

## Installation
1. Copier ce dossier dans `/custom/tickets/`
2. Aller dans Administration > Modules et activer "Module Tickets Personnalisé"

## Assignation des projets
Un projet est considéré comme assigné à l'utilisateur si :
- L'utilisateur est le créateur du projet
- L'utilisateur a enregistré du temps sur des tâches du projet

## Utilisation
1. Aller sur un projet
2. Cliquer sur "Nouveau ticket" ou utiliser le menu Tickets
3. Sélectionner un projet dans la liste
4. Créer le ticket - il sera automatiquement lié au projet
