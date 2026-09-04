# Ensemble de correctifs et de fonctionnalités ajoutés sur chaque  branches
## 1. Branche  fix/prod-feedback-fixes
***L'ensemble  de  ces correctifs a été testé et  est fonctionel, à les verifier après la mise en prod.***
#### - sur planity : 
 - le filtre année de kpi recrutement : lorsque la date de retour de candidat est  dans  un nouvel an alors  que  sa date de retour  est  dans  l'année  précédente  alors  ce candidat sera  affiché  dans  l'année suivante  en utilisant  le  filtre année.
 - Sur vivier candidats paris/lille et candidatures  a traiter it paris/lille : on a  ajouté  la  signature  de  Mélina  sur  les  modèles de  mail envoyés automatiquement  aux  candidats  lorsq  de  changement de  status  recruté, presenté au client et vivier sur les viviers et vivier sur  les  candidatures a traiter. 
 - erreur de chargement sur les besoins lille/paris a été corrigé, le chargement des candidatures vérifiait un droit Monday non utilisé ailleurs dans Planity (monday/myobject/read), ce qui bloquait les utilisateurs qui n'ont pas les droits admin, du coup 
 le contrôle a été aligné sur l’accès Planity : tout utilisateur connecté pouvant ouvrir Planity peut charger les candidatures.
 - Sur tous  les  espaces : les lignes ajoutées ( candidats ou clients) s'affichent en premier sur les  tableaux. 
 - le popup de confirmation de transfert du candidat a été amélioré : le titre a été changé  pour  ***transfert réussi*** au lieu de ***T24***, le  nom de candidat transféré est  mentionné sur  le poupup + un style css. 
 - Le css du filtre de kpi recrutement est durci et  le  ***Aucune donnée*** de ***Délai moyen de réponse client*** s'affiche correctement. 
 - **une autre précision** : si une  ligne  n'as  pas une  date de retour alors elle peut pas etre utilisé pour les stats qui dépendent pas sur le filtre de periode ou année mais  sans  ces deux filtres là elle est utilisé pour toutes les stats sauf le  delai moyen. 

 - Le ***Kanban planity*** a été renommé en ***Liste des besoins***.
 - **Sur le Knaban planity** : 
    - possiblité de supprimer une étiquette.
    - lorsque divers destinataires sont utilisés a chaque fois pour notifier le meme évènement, on ne crée pas une deuxieme étiquette mais les noms des nouveaux users seront ajouté dans le destinataire de l'étiquette exisatante.
    - Lorsque on clique sur ***Notifier*** un évenement, un popup avec la liste des destinataires possibles configuré sur le  module Tiers est affiché. 
    - le style css de popup est amélioré.
    - cette fonction existe déja mais je crois que je l'ai jamais mentionnée : lorsque  le status de l'étiquette est changé (étiquette déplacée) alors meme le status de lévenement sera changé dans sa fiche et l'inverse.
    et aussi pour la  gestion des droits des utilisateurs sur ces notofications, il faut combiner entre les droits sur tiers et droits sur évènemts. 
 - **Espace Clients - Besoins** : un nouveau espace ajouté pour indiquer pour chaque  client ses besoins pour éviter la saisi manuel. 
      - il faut renommer sur Kpi recrutement les deux  colonnes Client et Besoin avant la mise en prod, deux  colonnes Client et Besoins vont etre ajouté via ce hook.la colonne  client importe tous  les clients de la base client-besoins et la colonne besoins importe les besoins de la base selon le  client choisi. 
      - ***sur besoins client lille et paris*** :   Pour éviter la  perte de données  lors de la mise  en prod, j'ai integré la  nouvelle  fonctionnalité qui relient les besoins de chaque client a ses candidats  dans le dernier tableau sur  les deux espaces donc en prod ça sera  possible après verification de supprimer le premier tableau sur les deux espaces.
      - sur besoins- clients : une archive est ajouté et des colonnes mail, tèl.. .
 #### - sur le module tickets : 
  - le tableau de liste des tickets qui déborde est maintenant fixé. 
  - le problème de mails automatiques sur les tickets a été corrigé en regardant les logs ***(point4)***, à tester en prod.
 
  
## 2. Branche  T16 
**ça concerne les emails envoyés automatiquement aux candidats depuis 2 ans sur planity.**
- si la  date de  création de la fiche candidat date deuis 2 ans ou plus alors  un email est envoyé a ce candidat si son mail est bien rensigné  sur la colonne mail. 
- si le  mail s'envoie avec un succès alors le  mail passe sur  la  fiche  candidat sinon une alerte est affichée sur cette fiche avec une  petite alerte devant son nom dans la  premiere colonne pour voir l'erreur sans ouvrrir la fiche. 
- dans  le cas de l'echec des autres tentatives d'envoie s'effectue chaque 24h jsq ce que le mail sera envoyé.
- le cron de  dolibarr sur taches palinifiées éxécute le hook chaque 24h.

## 3. Branche  feature/ticket-notifications 
- des emails automatiques envoyés aux tiers pour les status  ***Lu, en attente de retour***. 
- pour le status en attente de retour  un poup pour modifier le  mail d'nevoie est affiché. 
- un champ ***Niveau*** est ajouté sur le formulaire  de création du tickets.
- les emails envoyés aux tiers ne contiennent plus l'i'nfo ***assigné à*** et est remplacé par ***Niveau***. 
- Le correctif du problème vu dans les logs Dolibarr de production a aussi été importé dans cette branche. Cela sécurise les mails des nouveaux statuts ***Lu*** et ***En attente de retour***.
- En local, si Office365 refuse l'envoi avec une erreur `Invalid domain name` ou `refused the EHLO command` sur `localhost:8080`, ajouter dans ***Configuration > Divers*** la constante `MAIL_SMTP_USE_FROM_FOR_HELO` avec la valeur `1` ou le domaine de l'expéditeur, par exemple `groupevitaminet.com`.

## 4. Logs Dolibarr conceranant le problème de mails non envoyés au tiers lors de la création des tickets et le  passage du status à en cours et au user assigné lors du passage a  en cours et  fermeture des tickets
 [les logs de test de toute la chaine : création - status en cours - fermeture en notifiant tous le monde - réouverture - fermeture en notifiant que le tier](/dolibarr-prod-2026-08-26.log)

***Ce que les logs Dolibarr ont montré :***

Les logs montrent que le SMTP fonctionne bien en production. Quand Dolibarr arrive réellement à appeler `CMailFile`, l'envoi se termine avec `mail end success`. Donc le problème ne vient pas de Gmail, Outlook ou de la configuration SMTP, mais du moment où le code récupère les destinataires.

A la création du ticket, le mail au tiers ne part pas parce que le trigger cherche le contact externe trop tôt. Dans les logs, on voit d'abord `TICKET_CREATE`, puis `Custom ticket email skipped create ... no ticket contact recipient`, et seulement après on voit l'ajout du contact externe au ticket. Donc au moment où l'email de création est préparé, le ticket n'a pas encore son contact tiers lié. Par contre, le mail à la personne assignée part bien, car l'utilisateur assigné est déjà connu au moment de la création.

Au passage du ticket à `En cours`, les logs montrent que le statut est bien mis à jour avec `fk_statut = 3`, mais aucun email tiers n'est envoyé. Le code se basait sur les informations portées par l'objet Dolibarr, alors que dans ce flux de production le statut demandé est surtout disponible dans la requête `confirm_set_status` avec le champ `new_status`.

A la fermeture, le mail au tiers part correctement. Par contre, quand on choisit de notifier tout le monde, la personne assignée ne reçoit pas le mail. La logique custom sait ajouter l'assigné seulement quand elle reçoit le choix `contactid = -2`. En production, ce choix n'était pas toujours récupéré depuis le contexte Dolibarr, donc seul le contact tiers était notifié.

***Correction appliquée :***

La correction est minimale et se trouve dans `interface_100_modTicket_TicketsEmail.class.php`. On garde toute la logique existante, mais on ajoute deux fallbacks : si `contact_id` n'est pas présent dans le contexte Dolibarr, on lit `contactid` depuis la requête ; et si le nouveau statut n'est pas fiable dans l'objet, on lit `new_status` depuis l'action `confirm_set_status`. Cela permet de couvrir la création, le passage à `En cours`, et la fermeture avec le choix "tout le monde", sans modifier la configuration SMTP ni les modèles d'email.

## 5. Branche   feat/ticket-template 
cette branche concerne la liste des tickets sur le module ***Tickets***.
- Possibilité de filtrer l'ensemble des tickets disponibles par leurs modèles.ou sinon ça affiche tous les tickets de tous les modèles.

## 6. Branche  T1 
***La configuration à faire***
 - Activer le module ***outlooksync***.  
 - demander à l'équipe support un nouveau ***secret*** pour l'***application entra id*** lors de la mise en prod (ne pas  utiliser celui de développement) et d'ajouter tous les utilisateurs dolibarr qui veulent la synchronisation dolibarr outlook (franck, michael et mélina).
 - créer un ***.env*** à la racine du projet sur le serveur de la prod dans le quel on mettra le secret dans la variable ***OUTLOOKSYNC_CLIENT_SECRET*** : 
 ``` OUTLOOKSYNC_CLIENT_SECRET=Secret ```
 - ne laisser pas d'espace après le ***=***.
 - Limiter les accès au ***.env*** qu'au propriétaire en applicant des permissions stricts `chmod 600 .env` sur le fichier ***.env***.
 - Ne  jamais  loguer le secret et le reste de règles de sécutrité pour proteger le secret sur le serveur.
 - Dans le cas de synchronisation ***outlook -> dolibarr*** : le cron se lance toutes les 5 minutes. Pour le consulter, aller dans ***outils d'administations -> travaux planifiés -> OutlooksyncImportOutlookEvents***. Il est possible de le lancer  manuellment pour tester la synchronisation Outlook.
 - le cron OutlooksyncImportOutlookEvents doit être actif.
 - Dans le cas de synchronisation ***dolibarr -> outlook*** : la synchronisation est effectuée automatiquement via l’API Microsoft Graph, qui permet de créer, modifier ou supprimer dans Outlook les événements provenant de l’agenda Dolibarr.
 - Dans la page de configuration du module ***outlooksync***, renseigner :
   - ***Tenant ID***.
   - ***Application ID***.
   - ***Expiration du secret*** : mettre la date d'expiration du secret fourni par l'équipe support. Le secret doit être régénéré et remplacé dans le fichier ***.env*** avant cette date.
   - ***Boîtes internes autorisées*** : mettre les emails exacts des utilisateurs Dolibarr concernés par la synchronisation, séparés par des virgules.
 - La liste ***Boîtes internes autorisées*** dans Dolibarr sert uniquement de garde-fou local : elle limite les boîtes que le module essaie de synchroniser. Le vrai droit d'accès aux calendriers Outlook reste configuré côté Microsoft Entra ID / Application Access Policy.
 - Vérifier que chaque utilisateur Dolibarr synchronisé possède le même email que sa boîte Outlook. Sinon le module ne pourra pas retrouver correctement l'organisateur ou les participants internes.
 - Côté Microsoft Entra ID / Graph API :
   - l'application doit avoir l'autorisation applicative ***Calendars.ReadWrite*** (déja fait).
   - Vérifier côté Microsoft 365 que l’accès Graph de l’application est limité uniquement aux boîtes concernées, idéalement via une Application Access Policy Exchange.
 - Après création ou modification du fichier ***.env***, redémarrer le conteneur Dolibarr (sinon le secret ne sera pas pris en compte) :
 ```bash
 docker compose up -d --force-recreate dolibarr
 ```
 - Vérifier que la variable est bien chargée dans le conteneur sans afficher le secret :
 ```bash
 docker exec dolibarr_app sh -lc 'test -n "$OUTLOOKSYNC_CLIENT_SECRET" && echo OK || echo MISSING'
 ```
 - Pour valider la synchronisation après mise en prod :
   - créer un événement manuel dans Dolibarr avec un utilisateur synchronisé comme organisateur, puis vérifier qu'il apparaît dans Outlook.
   - créer un événement dans Outlook depuis une boîte autorisée, lancer le cron manuellement, puis vérifier qu'il apparaît dans Dolibarr.
 - En cas d'erreur, vérifier les logs Dolibarr et les colonnes ***last_error*** des tables ***llx_outlooksync_event*** et ***llx_outlooksync_state***.

## 7. Branche  T2 
pour voir que les évènements crées manuellement ou tout autre type, il suffit d'utiliser le filtre de l'agenda.
[le filtre](/image.png)

## 8. Branche  T13
**ça concerne les KPI support projets/tickets/tâches.**

***Configuration / points à vérifier en production***
 - Les modules Dolibarr ***Tickets*** et ***Projets*** doivent être activés, sinon la page ***Support KPI*** n'est pas accessible. La page se trouve dans le module ***Tickets***.
 - Si on ne voit rien après déploiement, vérifier que le module ***Tickets personnalisé*** est activé/réactivé, que les menus Dolibarr sont rafraîchis et que l'utilisateur a le droit de lecture sur le module.
 - Les KPI prennent en compte uniquement les projets ouverts, avec leurs tickets et leurs tâches.
 - Le délai moyen de résolution est calculé avec le temps consommé renseigné sur les tâches projet : `llx_projet_task.duration_effective`.
 - Il faut renseigner le temps de consommation sur les tâches. le temps consommé des tickets est récupéré depuis les tâches.
 - Les tâches sans temps consommé (`duration_effective` vide ou à 0) ne sont pas prises dans ce calcul.


## 8. Branche  T19 - **Suivi des mails candidats via Microsoft Graph**

J'ai remplacé l'ancienne logique basée sur le collecteur IMAP Dolibarr. Le module ne lit plus les mails avec IMAP et ne dépend plus des jetons OAuth IMAP Dolibarr.

Le nouveau fonctionnement :
- Mélina envoie le mail depuis sa boîte Outlook.
- elle met le candidat en destinataire normal.
- elle met en ***CCI*** l'adresse de suivi du candidat,`suivi-candidats+TOKEN@domaine.fr` qui se retrouve dans sa fiche candidat.
- le cron Dolibarr lit les éléments envoyés de la boîte Mélina avec Microsoft Graph ;
- il récupère le `TOKEN` dans `bccRecipients` ;
- il retrouve la fiche candidat dans Planity ;
- il ajoute le mail dans le suivi du candidat avec les pièces jointes ;
- il garde une trace du mail importé pour éviter les doublons.

***Modules Dolibarr à activer***
- Activer le module ***Planity / Monday***.
- Activer le module ***Travaux planifiés / Cron***.
- Le module ***Email collector*** n'est plus nécessaire pour ce besoin.

***Configuration Microsoft / équipe réseaux***
- L'application Microsoft Entra ID de production doit avoir l'autorisation applicative Graph ***Mail.Read (déja fait)***.
- On ajoute à l'application toutes les boîtes Outlook des recruteurs (j'ai demandé à la dsi pour mélina) pour pouvoir les lire..
- Demander un secret de production différent du secret de développement(le meme pour la synchro outlook).
- Pour la boite de suivi Outlook,il fautl'activation du sous-adressage pour accepter les adresses du type `suivi-candidats+TOKEN@domaine.fr`.

***Fichier `.env` en production***

rajoute : 
```env
MONDAY_GRAPH_CLIENT_SECRET=Secret
```
Le `docker-compose.yaml` doit injecter ce fichier dans les services `dolibarr` et `dolibarr_cron` avec `env_file: .env`.

Après modification du `.env`, recréer les conteneurs :

```bash
docker compose up -d --force-recreate dolibarr dolibarr_cron
```

***Configuration dans Dolibarr***

Aller dans  ***Planity/Monday -> Configurer***:

Renseigner :
- `MONDAY_GRAPH_INBOUND_ENABLE` : mettre ***Oui***.
- `MONDAY_GRAPH_TENANT_ID` : Tenant ID Microsoft.
- `MONDAY_GRAPH_CLIENT_ID` : Application ID Microsoft.
- `MONDAY_GRAPH_RECRUITER_MAILBOXES` : boîte(s) recruteur(s) à lire, séparées par des virgules.
- `MONDAY_INBOUND_EMAIL_BASE` : adresse de suivi de base, `suivi-candidats@domaine.fr`.
- `MONDAY_GRAPH_BOOTSTRAP_LOOKBACK_DAYS` : mettre `7` pour limiter le premier import aux 7 derniers jours.
- `MONDAY_INBOUND_ATTACHMENT_MAX_SIZE` : mettre `26214400` pour limiter une pièce jointe à 25 Mo.
- `MONDAY_INBOUND_ATTACHMENT_MAX_COUNT` : mettre `20 ou moins` pour limiter le nombre de pièces jointes par mail.
- `MONDAY_INBOUND_EMAIL_MAX_SIZE` : mettre `104857600` pour limiter le total des pièces jointes d'un mail à 100 Mo.
- `MONDAY_INBOUND_ATTACHMENT_FORBIDDEN_EXTENSIONS` : mettre `exe,msi,bat,cmd,com,scr,ps1,vbs,js,jar,dll,iso,sh,run,bin,php,phtml,html,htm,htaccess` à rajouter d'autres extensions malveillantes.

En local ou en prod, `MONDAY_INBOUND_EMAIL_BASE` peut être une adresse Gmail, car le module ne lit pas cette boîte. Il lit seulement les éléments envoyés de la boîte Outlook configurée.

***Cron Dolibarr***
Le cron utilisé sur Travaux planifiés est :

`MondayGraphInboundSentItemsSync`
Il doit être actif en production et configuré toutes les 5 minutes.

Ce cron lit :

`/users/{boite-recruteur}/mailFolders('SentItems')/messages/delta`

Il utilise le `deltaLink` Graph pour ne récupérer ensuite que les nouveaux mails envoyés.

***Test après mise en production***
- Ouvrir une fiche candidat dans Planity.
- Copier son adresse de suivi, par exemple `suivi-candidats+a8f42c91@domaine.fr`.
- Depuis la boîte Outlook de la recruteuse configurée dans `MONDAY_GRAPH_RECRUITER_MAILBOXES`, envoyer un mail de test :
  - À : adresse du candidat ou adresse de test.
  - CCI : adresse de suivi avec le token.
  - Objet : test suivi candidat.
  - Ajouter une pièce jointe de test.
- Lancer le cron `MondayGraphInboundSentItemsSync` manuellement.
- Vérifier dans la fiche candidat que le mail apparaît dans les commentaires.
- Vérifier que les pièces jointes apparaissent aussi sur la fiche candidat.
- Relancer le cron une deuxième fois : le mail ne doit pas être importé une deuxième fois.
- Supprimer le mail/commentaire depuis la fiche candidat, puis relancer le cron : le mail ne doit pas revenir.
- Supprimer une pièce jointe depuis la fiche candidat, puis relancer le cron : la pièce jointe ne doit pas revenir.
- Si le nombre  de pièces jointes autorisées est dépassé dans le mail donc aucune pièce ne passera sur la fiche détails.
 
***Ce qu'il faut surveiller***
- Les logs Dolibarr en cas d'erreur du cron.
- La table `llx_monday_graph_sync_state` :
  - `delta_link` doit être renseigné après une synchronisation réussie.
  - `last_success_at` doit avancer.
  - `last_error` doit être vide.
- La table `llx_monday_inbound_email` :
  - elle contient les mails déjà importés ;
  - elle évite les doublons ;
  - elle garde la trace même si le commentaire est supprimé de la fiche candidat.
- Le cron `MondayGraphInboundSentItemsSync` :
  - dernier code retour à `0` ou positif ;
  - pas de `Erreur inconnue` ;
  - prochaine exécution cohérente.

***Points d'attention***
- La boîte configurée dans `MONDAY_GRAPH_RECRUITER_MAILBOXES` doit être exactement la boîte depuis laquelle le mail est envoyé.
- Le mail de suivi doit être en CCI avec le token.
- L'application Entra ID doit avoir `Mail.Read` sur toutes les boîtes recruteuses.
- Si Graph retourne une erreur `403`, vérifier les droits ou l'Application Access Policy.
- Si Graph retourne une erreur `401`, vérifier le Tenant ID, l'Application ID et le secret.
- Si rien ne s'importe mais qu'il n'y a pas d'erreur, vérifier que le mail est bien dans les éléments envoyés et que l'adresse de suivi est bien en CCI.
- Si le premier lancement importe trop d'historique, réduire `MONDAY_GRAPH_BOOTSTRAP_LOOKBACK_DAYS`.
