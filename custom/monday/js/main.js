$(function(){
  $('.side-nav .vmenu').prepend(window.leftmenu || '');
  const token = window.formtoken;
  const userId = window.userId;

  $(document).on('submit', 'form', function(e) {
    const form = $(this);
    const newWorkspaceInput = form.find('input[name="new_workspace"]');
    
    if (newWorkspaceInput.length > 0) {
      e.preventDefault();
      const workspaceName = newWorkspaceInput.val().trim();
      if (!workspaceName) return;
      
      const fd = new FormData();
      fd.append('new_workspace', workspaceName);
      fd.append('token', token);
      fd.append('ajax', '1'); 
      
      fetch('', {
        method: 'POST',
        body: fd
      })
      .then(response => {
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
          return response.json();
        } else {
          throw new Error('Response is not JSON');
        }
      })
      .then(data => {
        // Ajouter le nouvel espace à la liste avec le vrai ID
        const newItem = `<li class="workspace-item" data-id="${data.id}" style="padding:8px;cursor:pointer;">${data.label}</li>`;
        $('#workspace-list').append(newItem);
        
        // Vider le champ de saisie
        newWorkspaceInput.val('');
        
        console.log('Espace ajouté avec succès:', data);
      })
      .catch(error => {
        console.error('Erreur lors de l\'ajout de l\'espace:', error);
        // Fallback : ajouter avec un ID temporaire et recharger
        const newId = Date.now();
        const newItem = `<li class="workspace-item" data-id="${newId}" style="padding:8px;cursor:pointer;">${workspaceName}</li>`;
        $('#workspace-list').append(newItem);
        newWorkspaceInput.val('');
        
        // Recharger la page après un délai pour obtenir les vrais IDs
        setTimeout(() => location.reload(), 500);
      });
    }
  });

  // Variables globales pour le panneau
  let currentTaskId = null;

  window.saveCellValue = function(input) {
    const taskId = $(input).data('task');
    const columnId = $(input).data('column');
    const value = $(input).val();
    
    if($(input).is('select')) {
      applySelectColor($(input));
    }
    
    const fd = new FormData();
    fd.append('save_cell_task', taskId);
    fd.append('save_cell_column', columnId);
    fd.append('save_cell_value', value);
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd});
  };

  window.validateNumberInput = function(input) {
    const value = input.value;
    const allowedPattern = /^[0-9€$.,\s-]*$/;
    
    if (!allowedPattern.test(value)) {
      input.value = value.replace(/[^0-9€$.,\s-]/g, '');
    }
  };

  window.openUserSelector = function(cell) {
    const $cell = $(cell);
    const taskId = $cell.data('task');
    const columnId = $cell.data('column');
    
    fetch('?users_list')
      .then(r=>r.json())
      .then(users=>{
        const currentUserId = $cell.find('select').val();
        
        const modal = $(`
          <div id="user-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;">
            <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);min-width:400px;max-height:80vh;overflow-y:auto;">
              <h3>Assigner à un utilisateur</h3>
              
              <div id="users-list" style="margin:15px 0;">
                <div class="user-option ${!currentUserId ? 'selected' : ''}" data-user-id="" style="display:flex;align-items:center;gap:10px;margin-bottom:8px;padding:10px;border:2px solid ${!currentUserId ? '#007cba' : 'transparent'};background:${!currentUserId ? '#f0f8ff' : '#f9f9f9'};border-radius:6px;cursor:pointer;">
                  <div class="user-avatar" style="background:#999;">--</div>
                  <span style="font-style:italic;color:#666;">Non assigné</span>
                </div>
                ${users.map(user => {
                  const isSelected = currentUserId == user.id;
                  const initials = user.name.split(' ').map(n => n[0]).join('').substr(0, 2).toUpperCase();
                  return `
                    <div class="user-option ${isSelected ? 'selected' : ''}" data-user-id="${user.id}" style="display:flex;align-items:center;gap:10px;margin-bottom:8px;padding:10px;border:2px solid ${isSelected ? '#007cba' : 'transparent'};background:${isSelected ? '#f0f8ff' : '#f9f9f9'};border-radius:6px;cursor:pointer;">
                      <div class="user-avatar" title="${user.name}">${initials}</div>
                      <div>
                        <div style="font-weight:bold;">${user.name}</div>
                        <div style="font-size:12px;color:#666;">${user.email || user.login}</div>
                      </div>
                    </div>
                  `;
                }).join('')}
              </div>
              
              <div style="margin-top:20px;text-align:right;display:flex;gap:10px;justify-content:flex-end;">
                <button id="save-user" style="padding:8px 16px;background:#007cba;color:white;border:none;cursor:pointer;border-radius:4px;">Assigner</button>
                <button id="cancel-user" style="padding:8px 16px;background:#ccc;border:none;cursor:pointer;border-radius:4px;">Annuler</button>
              </div>
            </div>
          </div>
        `);
        
        $('body').append(modal);
        
        $('.user-option').click(function(){
          $('.user-option').removeClass('selected').css({
            'border': '2px solid transparent',
            'background': '#f9f9f9'
          });
          $(this).addClass('selected').css({
            'border': '2px solid #007cba',
            'background': '#f0f8ff'
          });
        });
        
        $('#save-user').click(function(){
          const selectedUserId = $('.user-option.selected').data('user-id') || '';
          
          const fd = new FormData();
          fd.append('save_cell_task', taskId);
          fd.append('save_cell_column', columnId);
          fd.append('save_cell_value', selectedUserId);
          fd.append('token', token);
          
          fetch('', {method: 'POST', body: fd}).then(()=>{
            modal.remove();
            
            // Recharger les groupes pour mettre à jour l'affichage
            const $activeWorkspace = $('.workspace-item').filter(function() {
              return $(this).css('background-color') === 'rgb(0, 124, 186)';
            });
            
            if ($activeWorkspace.length > 0) {
              const wsId = $activeWorkspace.data('id');
              loadGroups(wsId);
            }
          });
        });
        
        $('#cancel-user').click(function(){
          modal.remove();
        });
        
        modal.click(function(e){
          if(e.target === modal[0]) {
            modal.remove();
          }
        });
      });
  };

  window.openTagsSelector = function(cell) {
    const $cell = $(cell);
    const taskId = $cell.data('task');
    const columnId = $cell.data('column');
    
    fetch(`?column_options=${columnId}`)
      .then(r=>r.json())
      .then(options=>{
        const selectedTags = [];
        $cell.find('.tag-item').each(function(){
          const tagId = parseInt($(this).data('tag-id'));
          if(tagId && !selectedTags.includes(tagId)) {
            selectedTags.push(tagId);
          }
        });
        
        console.log('Tags actuellement sélectionnés:', selectedTags);
        
        const modal = $(`
          <div id="tags-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;">
            <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);min-width:400px;max-height:80vh;overflow-y:auto;">
              <h3>Sélectionner des étiquettes</h3>
              
              <div id="available-tags" style="margin:15px 0;">
                ${options.map(opt => {
                  const isSelected = selectedTags.includes(parseInt(opt.id));
                  console.log(`Option ${opt.label} (ID: ${opt.id}) - Sélectionnée: ${isSelected}`); // Debug
                  return `
                    <div class="tag-option ${isSelected ? 'selected' : ''}" data-tag-id="${opt.id}" style="display:inline-block;margin:5px;padding:6px 12px;background:${opt.color || '#87CEEB'};color:white;border-radius:15px;cursor:pointer;border:2px solid ${isSelected ? '#000' : 'transparent'};">
                      ${opt.label}
                    </div>
                  `;
                }).join('')}
              </div>
              
              ${options.length === 0 ? '<p style="text-align:center;color:#666;font-style:italic;">Aucune étiquette disponible. Utilisez "Gérer options" dans le menu de la colonne pour en créer.</p>' : ''}
              
              <div style="margin-top:20px;text-align:right;display:flex;gap:10px;justify-content:flex-end;">
                <button id="save-tags" style="padding:8px 16px;background:#007cba;color:white;border:none;cursor:pointer;border-radius:4px;">Sauvegarder</button>
                <button id="cancel-tags" style="padding:8px 16px;background:#ccc;border:none;cursor:pointer;border-radius:4px;">Annuler</button>
              </div>
            </div>
          </div>
        `);
        
        $('body').append(modal);
        
        $('.tag-option').click(function(){
          $(this).toggleClass('selected');
          if($(this).hasClass('selected')) {
            $(this).css('border', '2px solid #000');
          } else {
            $(this).css('border', '2px solid transparent');
          }
        });
        
        $('#save-tags').click(function(){
          const selectedTagIds = [];
          $('.tag-option.selected').each(function(){
            selectedTagIds.push(parseInt($(this).data('tag-id')));
          });
          
          console.log('Tags à sauvegarder:', selectedTagIds);
          
          const fd = new FormData();
          fd.append('save_cell_task', taskId);
          fd.append('save_cell_column', columnId);
          fd.append('save_cell_value', JSON.stringify(selectedTagIds));
          fd.append('token', token);
          
          fetch('', {method: 'POST', body: fd}).then(()=>{
            modal.remove();
            
            // Recréer dynamiquement l'affichage des tags sans rechargement complet
            let tagsHtml = `
              <div class="selected-tags" style="display:flex;flex-wrap:wrap;gap:3px;margin-bottom:5px;">
            `;
            
            fetch(`?column_options=${columnId}`)
              .then(r=>r.json())
              .then(allOptions=>{
                selectedTagIds.forEach(tagId => {
                  const tag = allOptions.find(opt => parseInt(opt.id) === tagId);
                  if(tag) {
                    // Utiliser la couleur définie pour le tag
                    tagsHtml += `
                      <span class="tag-item" data-tag-id="${tag.id}" style="background:${tag.color || '#87CEEB'};color:white;padding:2px 6px;border-radius:12px;font-size:11px;display:flex;align-items:center;gap:4px;">
                        ${tag.label}
                        <span class="remove-tag" onclick="removeTag(event, this)" style="cursor:pointer;font-weight:bold;">×</span>
                      </span>
                    `;
                  }
                });
                
                tagsHtml += `
                  </div>
                  <div class="add-tag-hint" style="color:#999;font-size:10px;font-style:italic;">+ Cliquer pour ajouter des étiquettes</div>
                `;
                
                // Mettre à jour directement le contenu de la cellule
                $cell.html(tagsHtml);
              });
          });
        });
        
        $('#cancel-tags').click(function(){
          modal.remove();
        });
        
        modal.click(function(e){
          if(e.target === modal[0]) {
            modal.remove();
          }
        });
      });
  };

  window.removeTag = function(event, tagElement) {
    event.stopPropagation();
    const $cell = $(tagElement).closest('.tags-cell');
    const taskId = $cell.data('task');
    const columnId = $cell.data('column');
    
    $(tagElement).closest('.tag-item').remove();
    
    const remainingTags = [];
    $cell.find('.tag-item').each(function(){
      remainingTags.push($(this).data('tag-id'));
    });
    
    const fd = new FormData();
    fd.append('save_cell_task', taskId);
    fd.append('save_cell_column', columnId);
    fd.append('save_cell_value', JSON.stringify(remainingTags));
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd}).then(()=>{
      console.log('Tag supprimé et sauvegardé');
    });
  };

  window.updateDeadline = function(input) {
    const $cell = $(input).closest('.deadline-cell');
    const taskId = $cell.data('task');
    const columnId = $cell.data('column');
    
    const startDate = $cell.find('.deadline-start').val();
    const endDate = $cell.find('.deadline-end').val();
    const value = `${startDate}|${endDate}`;
    
    let daysText = '';
    let daysClass = '';
    if(endDate) {
      const today = new Date();
      const end = new Date(endDate);
      const diffTime = end - today;
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      
      if(diffDays > 0) {
        daysText = `${diffDays} jour${diffDays > 1 ? 's' : ''} restant${diffDays > 1 ? 's' : ''}`;
        daysClass = diffDays <= 3 ? 'deadline-urgent' : (diffDays <= 7 ? 'deadline-warning' : 'deadline-ok');
      } else if(diffDays === 0) {
        daysText = "Aujourd'hui";
        daysClass = 'deadline-urgent';
      } else {
        daysText = `En retard de ${Math.abs(diffDays)} jour${Math.abs(diffDays) > 1 ? 's' : ''}`;
        daysClass = 'deadline-overdue';
      }
    }
    
    const $daysDiv = $cell.find('.days-remaining');
    $daysDiv.text(daysText).removeClass('deadline-urgent deadline-warning deadline-ok deadline-overdue').addClass(daysClass);
    
    const fd = new FormData();
    fd.append('save_cell_task', taskId);
    fd.append('save_cell_column', columnId);
    fd.append('save_cell_value', value);
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd});
  };

  function applySelectColor($select) {
    const selectedValue = $select.val();
    if(selectedValue) {
      const selectedOption = $select.find(`option[value="${selectedValue}"]`);
      const style = selectedOption.attr('style');
      if(style) {
        const color = style.match(/background:([^;]+)/)?.[1];
        if(color) {
          $select.css('background-color', color);
          return;
        }
      }
    }
    $select.css('background-color', 'transparent');
  }

  // Fonction pour ouvrir le panneau de détail d'une tâche
  window.openTaskDetail = function(taskId, taskName, groupName) {
    currentTaskId = taskId;
    
    $('#task-detail-title').text('Détail de la tâche');
    $('#task-name-display').text(taskName);
    $('#task-group-display').text(groupName);
    $('#task-created-display').text('Chargement...');
    
    $('#task-detail-panel').addClass('open');
    
    fetch(`?task_details=${taskId}`)
      .then(r => r.json())
      .then(task => {
        if (task.datec) {
          const createdDate = new Date(task.datec);
          const formattedDate = createdDate.toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
          });
          $('#task-created-display').text(formattedDate);
        } else {
          $('#task-created-display').text('Date non disponible');
        }
        
        $('#task-name-display').text(task.label);
        $('#task-group-display').text(task.group_label);
      })
      .catch(err => {
        console.error('Erreur lors du chargement des détails:', err);
        $('#task-created-display').text('Erreur de chargement');
      });
    
    loadComments(taskId);
    loadTaskFiles(taskId);
  };

  // Fonction pour fermer le panneau
  window.closeTaskDetail = function() {
    $('#task-detail-panel').removeClass('open');
    currentTaskId = null;
  };

  // Fonction pour charger les commentaires
  function loadComments(taskId) {
    fetch(`?task_comments=${taskId}`)
      .then(r => r.json())
      .then(comments => {
        const $commentsList = $('#comments-list');
        $commentsList.empty();
        
        if (comments.length === 0) {
          $commentsList.append(`
            <div class="no-comments" style="text-align:center;color:#666;font-style:italic;padding:20px;">
              Aucun commentaire pour cette tâche
            </div>
          `);
          return;
        }
        
        comments.forEach(comment => {
          const commentDate = new Date(comment.date);
          const formattedDate = commentDate.toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
          });
          
          const $comment = $(`
            <div class="comment-item" data-comment-id="${comment.id}">
              <div class="comment-header">
                <span class="comment-author">${comment.user_name}</span>
                <span class="comment-date">${formattedDate}</span>
              </div>
              <div class="comment-text">${comment.comment}</div>
              <div class="comment-actions">
                <button class="comment-action-btn edit-comment-btn" data-comment-id="${comment.id}">Modifier</button>
                <button class="comment-action-btn delete-comment-btn" data-comment-id="${comment.id}">Supprimer</button>
              </div>
            </div>
          `);
          
          $commentsList.append($comment);
        });
      })
      .catch(err => {
        console.error('Erreur lors du chargement des commentaires:', err);
      });
  }

  // Fonction pour charger les fichiers d'une tâche
  function loadTaskFiles(taskId) {
    console.log('loadTaskFiles appelée avec taskId:', taskId);
    fetch(`?task_files=${taskId}`)
      .then(r => {
        console.log('Réponse task_files reçue:', r.status);
        return r.json();
      })
      .then(files => {
        console.log('Fichiers reçus:', files);
        const $filesList = $('#task-files-list');
        $filesList.empty();
        
        if (files.length === 0) {
          $filesList.append(`
            <div class="no-files" style="text-align:center;color:#666;font-style:italic;padding:20px;">
              Aucun fichier pour cette tâche
            </div>
          `);
        } else {
          files.forEach(file => {
            const fileSize = formatFileSize(file.filesize);
            const fileIcon = getFileIcon(file.mimetype, file.original_name);
            
            const $fileItem = $(`
              <div class="task-file-item" data-file-id="${file.rowid}">
                <div class="task-file-info">
                  <a href="#" class="task-file-name" onclick="viewTaskFile(${file.rowid}, '${file.original_name}', '${file.mimetype}'); return false;">
                    ${fileIcon} ${file.original_name}
                  </a>
                  <div class="task-file-meta">${fileSize} • ${file.user_name || 'Inconnu'}</div>
                </div>
                <div class="task-file-actions">
                  <button class="task-delete-file" onclick="deleteTaskFile(${file.rowid})" title="Supprimer">×</button>
                </div>
              </div>
            `);
            
            $filesList.append($fileItem);
          });
        }
      })
      .catch(err => {
        console.error('Erreur lors du chargement des fichiers de tâche:', err);
        const $filesList = $('#task-files-list');
        $filesList.html(`
          <div style="text-align:center;color:#dc3545;padding:20px;">
            Erreur lors du chargement des fichiers
          </div>
        `);
      });
  }

  // Fonction pour formater la taille des fichiers
  function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  // Fonction pour obtenir l'icône du fichier
  function getFileIcon(mimetype, filename) {
    if (!mimetype && filename) {
      const ext = filename.split('.').pop().toLowerCase();
      switch(ext) {
        case 'pdf': return '📄';
        case 'doc':
        case 'docx': return '📝';
        case 'xls':
        case 'xlsx': return '📊';
        case 'zip':
        case 'rar': return '🗜️';
        case 'txt': return '📃';
        default: return '📎';
      }
    }
    
    if (mimetype) {
      if (mimetype.startsWith('image/')) return '🖼️';
      if (mimetype.startsWith('video/')) return '🎥';
      if (mimetype.startsWith('audio/')) return '🎵';
      if (mimetype.includes('pdf')) return '📄';
      if (mimetype.includes('word') || mimetype.includes('document')) return '📝';
      if (mimetype.includes('sheet') || mimetype.includes('excel')) return '📊';
      if (mimetype.includes('zip') || mimetype.includes('compressed')) return '🗜️';
    }
    
    return '📎';
  }

  // Fonction globale pour visualiser un fichier de tâche
  window.viewTaskFile = function(fileId, fileName, mimeType) {
    const isImage = mimeType && mimeType.startsWith('image/');
    const isPdf = mimeType && mimeType.includes('pdf');
    
    if (isImage || isPdf) {
      // Ouvrir dans une modale pour les images et PDFs
      const modal = $(`
        <div id="file-viewer-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:1001;display:flex;align-items:center;justify-content:center;">
          <div style="position:relative;max-width:90%;max-height:90%;background:white;padding:20px;border-radius:8px;">
            <button id="close-viewer" style="position:absolute;top:10px;right:10px;background:none;border:none;font-size:24px;cursor:pointer;">✖</button>
            <h4 style="margin:0 0 15px 0;">${fileName}</h4>
            ${isImage ? 
              `<img src="?download_file=${fileId}&type=task" style="max-width:100%;max-height:70vh;" alt="${fileName}">` :
              `<iframe src="?download_file=${fileId}&type=task" style="width:80vw;height:70vh;border:none;"></iframe>`
            }
          </div>
        </div>
      `);
      
      $('body').append(modal);
      
      $('#close-viewer').click(() => modal.remove());
      modal.click(function(e) {
        if (e.target === modal[0]) modal.remove();
      });
    } else {
      // Télécharger directement pour les autres types
      window.open(`?download_file=${fileId}&type=task`, '_blank');
    }
  };

  // Fonction globale pour supprimer un fichier de tâche
  window.deleteTaskFile = function(fileId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?')) {
      return;
    }
    
    const fd = new FormData();
    fd.append('delete_file_id', fileId);
    fd.append('type', 'task');
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd})
      .then(r => r.text())
      .then(response => {
        if (response === 'OK') {
          loadTaskFiles(currentTaskId);
        } else {
          alert('Erreur lors de la suppression');
        }
      });
  };

  // Fonction globale pour supprimer un fichier
  window.deleteFile = function(fileId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?')) {
      return;
    }
    
    const fd = new FormData();
    fd.append('delete_file_id', fileId);
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd})
      .then(r => r.text())
      .then(response => {
        if (response === 'OK') {
          $(`.file-item[data-file-id="${fileId}"]`).remove();
        } else {
          alert('Erreur lors de la suppression');
        }
      });
  };

  // Fonction pour ajouter un commentaire
  function addComment() {
    const commentText = $('#new-comment-text').val().trim();
    
    if (!commentText) {
      alert('Veuillez saisir un commentaire');
      return;
    }
    
    if (!currentTaskId) {
      alert('Erreur: aucune tâche sélectionnée');
      return;
    }
    
    const fd = new FormData();
    fd.append('add_comment_task', currentTaskId);
    fd.append('comment_text', commentText);
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd})
      .then(response => {
        console.log('Statut de la réponse:', response.status);
        const contentType = response.headers.get('content-type');
        console.log('Type de contenu:', contentType);
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Lire la réponse comme texte d'abord pour voir ce qu'on reçoit
        return response.text().then(text => {
          console.log('Réponse brute reçue:', text);
          
          if (contentType && contentType.includes('application/json')) {
            try {
              return JSON.parse(text);
            } catch (e) {
              console.error('Erreur de parsing JSON:', e);
              console.error('Texte qui ne peut pas être parsé:', text);
              throw new Error('La réponse n\'est pas du JSON valide');
            }
          } else {
            console.error('Type de contenu inattendu:', contentType);
            console.error('Réponse non-JSON reçue:', text);
            throw new Error('La réponse n\'est pas du JSON valide');
          }
        });
      })
      .then(comment => {
        console.log('Commentaire ajouté avec succès:', comment);
        $('#new-comment-text').val('');
        loadComments(currentTaskId);
      })
      .catch(err => {
        console.error('Erreur lors de l\'ajout du commentaire:', err);
        alert('Erreur lors de l\'ajout du commentaire: ' + err.message);
      });
  }

  // Gestionnaires d'événements pour le panneau
  $('#close-panel').click(closeTaskDetail);
  $('#add-comment-btn').click(addComment);

  $('#new-comment-text').keydown(function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
      addComment();
    }
  });

  // Gestionnaire pour modifier le nom de la tâche
  $('#edit-task-name').click(function() {
    const currentName = $('#task-name-display').text();
    const newName = prompt('Nouveau nom de la tâche:', currentName);
    
    if (!newName || newName === currentName) return;
    
    const fd = new FormData();
    fd.append('rename_task_id', currentTaskId);
    fd.append('rename_task_label', newName);
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd})
      .then(() => {
        // Mettre à jour le nom dans le panneau de détail
        $('#task-name-display').text(newName);
        
        // Mettre à jour le nom dans le tableau principal
        $(`tr[data-id="${currentTaskId}"] td:nth-child(3)`).text(newName);
        
        // Recharger les groupes pour être sûr que tout est à jour
        // Trouver l'espace de travail actuellement sélectionné (avec le nouveau style)
        const $activeWorkspace = $('.workspace-item').filter(function() {
          const $this = $(this);
          return $this.css('background-color') === 'rgb(0, 124, 186)' || // #007cba en RGB
                 $this.css('font-weight') === 'bold' ||
                 $this.css('font-weight') === '700';
        });
        
        if ($activeWorkspace.length > 0) {
          const wsId = $activeWorkspace.data('id');
          if (wsId) {
            console.log('Rechargement des groupes pour l\'espace:', wsId);
            loadGroups(wsId);
          }
        } else {
          console.log('Aucun espace actif trouvé, recherche alternative...');
          // Alternative : chercher par la présence de contenu dans main-content
          const $allWorkspaces = $('.workspace-item');
          if ($allWorkspaces.length > 0) {
            const wsId = $allWorkspaces.first().data('id');
            console.log('Utilisation du premier espace disponible:', wsId);
            loadGroups(wsId);
          }
        }
      })
      .catch(err => {
        console.error('Erreur lors de la modification du nom:', err);
        alert('Erreur lors de la modification du nom');
      });
  });

  // Gestionnaires pour les actions sur les commentaires
  $(document).on('click', '.edit-comment-btn', function() {
    const commentId = $(this).data('comment-id');
    const $commentItem = $(this).closest('.comment-item');
    const $commentText = $commentItem.find('.comment-text');
    const currentText = $commentText.text();
    
    const $editForm = $(`
      <div class="edit-comment-form">
        <textarea class="edit-comment-textarea">${currentText}</textarea>
        <div class="edit-comment-actions">
          <button class="save-edit-btn" data-comment-id="${commentId}">Sauver</button>
          <button class="cancel-edit-btn">Annuler</button>
        </div>
      </div>
    `);
    
    $commentText.hide();
    $commentItem.find('.comment-actions').hide();
    $commentItem.append($editForm);
  });

  $(document).on('click', '.save-edit-btn', function() {
    const commentId = $(this).data('comment-id');
    const $commentItem = $(this).closest('.comment-item');
    const newText = $commentItem.find('.edit-comment-textarea').val().trim();
    
    if (!newText) {
      alert('Le commentaire ne peut pas être vide');
      return;
    }
    
    const fd = new FormData();
    fd.append('edit_comment_id', commentId);
    fd.append('edit_comment_text', newText);
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd})
      .then(r => r.text())
      .then(response => {
        if (response === 'OK') {
          loadComments(currentTaskId);
        } else {
          alert('Erreur lors de la modification');
        }
      });
  });

  $(document).on('click', '.delete-comment-btn', function() {
    const commentId = $(this).data('comment-id');
    
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')) {
      return;
    }
    
    const fd = new FormData();
    fd.append('delete_comment_id', commentId);
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd})
      .then(r => r.text())
      .then(response => {
        if (response === 'OK') {
          loadComments(currentTaskId);
        } else {
          alert('Erreur lors de la suppression');
        }
      });
  });

  $(document).on('click', '.cancel-edit-btn', function() {
    const $commentItem = $(this).closest('.comment-item');
    $commentItem.find('.edit-comment-form').remove();
    $commentItem.find('.comment-text').show();
    $commentItem.find('.comment-actions').show();
  });

  // Gestionnaires pour les fichiers des tâches
  $(document).on('click', '#add-task-file-btn', function() {
    console.log('Bouton add-task-file-btn cliqué');
    console.log('currentTaskId:', currentTaskId);
    $('#task-file-input').click();
  });

  $(document).on('change', '#task-file-input', function() {
    const files = this.files;
    console.log('Files sélectionnés:', files.length);
    console.log('currentTaskId:', currentTaskId);
    
    if (files.length === 0) {
      console.log('Aucun fichier sélectionné');
      return;
    }
    
    if (!currentTaskId) {
      console.log('Erreur: currentTaskId est null');
      alert('Erreur: Aucune tâche sélectionnée');
      return;
    }
    
    // Créer un indicateur de chargement
    const $loadingDiv = $(`<div class="upload-progress">Upload en cours...</div>`);
    $('.task-file-upload-area').append($loadingDiv);
    
    // Upload des fichiers un par un
    Array.from(files).forEach((file, index) => {
      console.log('Upload du fichier:', file.name);
      const fd = new FormData();
      fd.append('upload_task_file', currentTaskId);
      fd.append('task_file', file);
      fd.append('token', token);
      
      fetch('', {method: 'POST', body: fd})
        .then(r => {
          console.log('Réponse reçue:', r.status);
          if (!r.ok) throw new Error('Upload failed');
          return r.json();
        })
        .then(result => {
          console.log('Résultat:', result);
          if (result.error) {
            alert('Erreur upload: ' + result.error);
          } else {
            // Recharger les fichiers de la tâche
            loadTaskFiles(currentTaskId);
          }
        })
        .catch(err => {
          console.error('Erreur upload:', err);
          alert('Erreur lors de l\'upload du fichier: ' + file.name);
        })
        .finally(() => {
          if (index === files.length - 1) {
            $loadingDiv.remove();
          }
        });
    });
    
    // Reset input
    $(this).val('');
  });

  $('#workspace-list').sortable({
    cursor:'pointer',
    update(){
      const order = $('#workspace-list .workspace-item').map((_,el)=>el.dataset.id).get();
      fetch('',{method:'POST',body:new URLSearchParams({
        reorder_workspaces: JSON.stringify(order),
        token: token
      })});
    }
  }).disableSelection();

  function initGroupSortable(){
    $('#group-list').sortable({
      cursor:'pointer',
      update(){
        const order = $('#group-list .group').map((_,el)=>el.dataset.id).get();
        fetch('',{method:'POST',body:new URLSearchParams({
          reorder_groups: JSON.stringify(order),
          token: token
        })});
      }
    }).disableSelection();
  }
  
  function initTaskSortable(){
    $('.group-body tbody').sortable({
      cursor:'pointer',
      update(){
        const order = $(this).children().map((_,tr)=>tr.dataset.id).get();
        fetch('',{method:'POST',body:new URLSearchParams({
          reorder_tasks: JSON.stringify(order),
          token: token
        })});
      }
    }).disableSelection();
  }

  $(document).on('click','.workspace-item', function(){
    const wsId    = this.dataset.id;
    const wsLabel = this.textContent;
    
    // Marquer cet espace comme sélectionné visuellement
    $('.workspace-item').css({
      'background-color': 'transparent',
      'color': 'black',
      'font-weight': 'normal'
    });
    $(this).css({
      'background-color': '#007cba',
      'color': 'white',
      'font-weight': 'bold'
    });
    
    $('#main-content').html(`
      <div style="display:flex;align-items:center;gap:10px;">
        <h2 style="margin:0;cursor:pointer;">${wsLabel}</h2>
        <button id="rename-btn" style="padding:2px 6px;">✎</button>
        <button id="delete-btn" style="padding:2px 6px;">✖</button>
      </div>
      <button id="add-group-btn" style="margin:1rem 0;">+ Ajouter un groupe</button>
      <div id="group-list"></div>
    `);

    $('#rename-btn').click(()=>{
      const n=prompt("Nouveau nom de l'espace :",wsLabel);
      if(!n) return;
      const fd=new FormData(); fd.append('rename_workspace_id',wsId);
      fd.append('rename_workspace_label',n); fd.append('token',token);
      
      console.log('Renommage de l\'espace:', wsId, 'vers:', n);
      
      fetch('',{method:'POST',body:fd})
        .then(response => {
          console.log('Réponse du serveur pour renommage:', response.status);
          return response.text(); // Récupérer la réponse pour debug
        })
        .then(responseText => {
          console.log('Contenu de la réponse:', responseText);
          
          // Mettre à jour le titre de l'espace dans l'interface
          $('#main-content h2').text(n);
          // Mettre à jour le nom dans la sidebar
          $(`.workspace-item[data-id="${wsId}"]`).text(n);
          
          console.log('Interface mise à jour avec succès');
        })
        .catch(error => {
          console.error('Erreur lors du renommage:', error);
        });
    });
    $('#delete-btn').click(()=>{
      if(!confirm('Supprimer cet espace ?')) return;
      const fd=new FormData(); fd.append('delete_workspace_id',wsId);
      fd.append('token',token);
      
      console.log('Suppression de l\'espace:', wsId);
      
      fetch('',{method:'POST',body:fd})
        .then(response => {
          console.log('Réponse du serveur pour suppression:', response.status);
          return response.text();
        })
        .then(responseText => {
          console.log('Contenu de la réponse:', responseText);
          
          // Supprimer l'espace de la sidebar
          $(`.workspace-item[data-id="${wsId}"]`).remove();
          // Retourner à l'état initial
          $('#main-content').html('<div style="text-align:center;padding:50px;color:#666;"><h3>Sélectionnez un espace de travail</h3><p>Choisissez un espace dans la liste de gauche pour commencer.</p></div>');
          
          console.log('Espace supprimé de l\'interface');
        })
        .catch(error => {
          console.error('Erreur lors de la suppression:', error);
        });
    });
    $('#add-group-btn').click(()=>{
      const n=prompt('Nom du groupe :');
      if(!n) return;
      const fd=new FormData(); fd.append('add_group_workspace_id',wsId);
      fd.append('group_label',n); fd.append('token',token);
      fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wsId));
    });

    loadGroups(wsId);
  });

  // Fonction globale pour charger les groupes d'un espace de travail
  function loadGroups(wid){
      fetch(`get_groups.php?wid=${wid}`)
        .then(r=>r.json()).then(groups=>{
          $('#group-list').empty();
          groups.forEach(g=>{
            fetch(`?columns_group_id=${g.id}`)
              .then(r=>r.json())
              .then(cols=>{
                let ths = `
                  <th style="border:1px solid #ddd;padding:4px;"></th>
                  <th style="border:1px solid #ddd;padding:4px;"></th>
                  <th style="border:1px solid #ddd;padding:4px;">Tâche</th>
                `;
                cols.forEach(c=>{
                  ths += `<th style="border:1px solid #ddd;padding:4px;position:relative;">
                            <span class="column-label" data-cid="${c.id}" style="cursor:pointer;">${c.label}</span>
                            <button class="column-menu-btn" data-cid="${c.id}" style="border:none;background:transparent;cursor:pointer;padding:0 2px;">⋮</button>
                            <div class="column-menu" style="display:none;position:absolute;right:0;top:22px;background:#fff;border:1px solid #ccc;z-index:10;">
                              <button class="rename-column-btn" data-cid="${c.id}" style="display:block;width:100%;border:none;background:transparent;cursor:pointer;padding:4px;">Renommer</button>
                              <button class="delete-column-btn" data-cid="${c.id}" style="display:block;width:100%;border:none;background:transparent;cursor:pointer;padding:4px;">Supprimer</button>
                              ${(c.type === 'select' || c.type === 'tags') ? `<button class="manage-options-btn" data-cid="${c.id}" style="display:block;width:100%;border:none;background:transparent;cursor:pointer;padding:4px;">Gérer options</button>` : ''}
                            </div>
                         </th>`;
                });
                ths += `<th style="border:1px solid #ddd;padding:4px;">
                          <button class="add-column-btn" data-gid="${g.id}" style="padding:2px 6px;">+</button>
                        </th>`;

                const $grp = $(`
                  <div class="group" data-id="${g.id}">
                    <div class="group-header" style="display:flex;justify-content:space-between;padding:8px;background:#f3f3f3;">
                      <div style="display:flex;align-items:center;gap:8px;">
                        <span class="group-toggle">▼</span>
                        <span class="group-label">${g.label}</span>
                      </div>
                      <div>
                        <button class="rename-group">✎</button>
                        <button class="delete-group">✖</button>
                      </div>
                    </div>
                    <div class="group-body" style="padding:10px;">
                      <table style="width:100%;border-collapse:collapse;margin-bottom:8px;">
                        <thead>
                          <tr style="background:#fafafa;">
                            ${ths}
                          </tr>
                        </thead>
                        <tbody></tbody>
                      </table>
                      <button class="add-row-btn" style="padding:4px 8px;">+ Ajouter tâche</button>
                    </div>
                  </div>
                `);

                if (g.collapsed === 1) {
                  $grp.find('.group-body').hide();
                  $grp.find('.group-toggle').text('►');
                }

                $('#group-list').append($grp);

                fetch(`?tasks_group_id=${g.id}`)
                  .then(r=>r.json())
                  .then(tasks=>{
                    tasks.forEach(t=>{
                      let tds = `
                        <td style="border:1px solid #ddd;padding:4px;text-align:center;">
                          <button class="rename-task-row" style="border:none;background:transparent;cursor:pointer;">✎</button>
                        </td>
                        <td style="border:1px solid #ddd;padding:4px;text-align:center;">
                          <button class="delete-task-row" style="border:none;background:transparent;cursor:pointer;">✖</button>
                        </td>
                        <td style="border:1px solid #ddd;padding:4px;">${t.label}</td>
                      `;
                      
                      fetch(`?task_cells=${t.id}`)
                        .then(r=>r.json())
                        .then(cells=>{
                          let cellPromises = [];
                          cols.forEach(c=>{
                            const cellValue = cells[c.id] || '';
                            
                            if(c.type === 'select') {
                              const promise = fetch(`?column_options=${c.id}`)
                                .then(r=>r.json())
                                .then(options=>{
                                  let selectHtml = `<select class="cell-select" data-task="${t.id}" data-column="${c.id}" 
                                                           style="border:none;background:transparent;width:100%;padding:2px;"
                                                           onchange="saveCellValue(this)">
                                                     <option value="">-- Choisir --</option>`;
                                  options.forEach(opt=>{
                                    const selected = cellValue == opt.id ? 'selected' : '';
                                    // Utiliser la couleur de l'option ou une couleur par défaut
                                    const optionColor = opt.color || '#87CEEB';
                                    selectHtml += `<option value="${opt.id}" ${selected} style="background:${optionColor};">${opt.label}</option>`;
                                  });
                                  selectHtml += '</select>';
                                  return selectHtml;
                                });
                              cellPromises.push(promise);
                            } else if(c.type === 'number') {
                              const inputHtml = `<input type="text" class="cell-input cell-number" 
                                data-task="${t.id}" 
                                data-column="${c.id}" 
                                value="${cellValue}" 
                                style="border:none;background:transparent;width:100%;padding:2px;text-align:right;"
                                pattern="[0-9€$.,\\s-]*"
                                onblur="saveCellValue(this)"
                                onkeydown="if(event.key==='Enter') saveCellValue(this)"
                                oninput="validateNumberInput(this)">`;
                              cellPromises.push(Promise.resolve(inputHtml));
                            } else if(c.type === 'tags') {
                              const promise = fetch(`?column_options=${c.id}`)
                                .then(r=>r.json())
                                .then(options=>{
                                  const selectedTags = cellValue ? JSON.parse(cellValue) : [];
                                  
                                  let tagsHtml = `
                                    <div class="tags-cell" data-task="${t.id}" data-column="${c.id}" style="min-height:30px;padding:3px;border:1px dashed #ddd;cursor:pointer;" onclick="openTagsSelector(this)">
                                      <div class="selected-tags" style="display:flex;flex-wrap:wrap;gap:3px;margin-bottom:5px;">
                                  `;
                                  
                                  selectedTags.forEach(tagId => {
                                    const tag = options.find(opt => opt.id == tagId);
                                    if(tag) {
                                      // Utiliser la couleur définie pour le tag
                                      tagsHtml += `
                                        <span class="tag-item" data-tag-id="${tag.id}" style="background:${tag.color || '#87CEEB'};color:white;padding:2px 6px;border-radius:12px;font-size:11px;display:flex;align-items:center;gap:4px;">
                                          ${tag.label}
                                          <span class="remove-tag" onclick="removeTag(event, this)" style="cursor:pointer;font-weight:bold;">×</span>
                                        </span>
                                      `;
                                    }
                                  });
                                  
                                  tagsHtml += `
                                      </div>
                                      <div class="add-tag-hint" style="color:#999;font-size:10px;font-style:italic;">+ Cliquer pour ajouter des étiquettes</div>
                                    </div>
                                  `;
                                  
                                  return tagsHtml;
                                });
                              cellPromises.push(promise);
                            } else if(c.type === 'deadline') {
                              const dates = cellValue ? cellValue.split('|') : ['', ''];
                              const startDate = dates[0] || '';
                              const endDate = dates[1] || '';
                              
                              let daysText = '';
                              let daysClass = '';
                              if(endDate) {
                                const today = new Date();
                                const end = new Date(endDate);
                                const diffTime = end - today;
                                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                                
                                if(diffDays > 0) {
                                  daysText = `${diffDays} jour${diffDays > 1 ? 's' : ''} restant${diffDays > 1 ? 's' : ''}`;
                                  daysClass = diffDays <= 3 ? 'deadline-urgent' : (diffDays <= 7 ? 'deadline-warning' : 'deadline-ok');
                                } else if(diffDays === 0) {
                                  daysText = "Aujourd'hui";
                                  daysClass = 'deadline-urgent';
                                } else {
                                  daysText = `En retard de ${Math.abs(diffDays)} jour${Math.abs(diffDays) > 1 ? 's' : ''}`;
                                  daysClass = 'deadline-overdue';
                                }
                              }
                              
                              const inputHtml = `
                                <div class="deadline-cell" data-task="${t.id}" data-column="${c.id}">
                                  <div style="display:flex;gap:5px;margin-bottom:3px;">
                                    <input type="date" class="deadline-start" value="${startDate}" 
                                           style="border:1px solid #ddd;padding:2px;font-size:10px;width:48%;"
                                           placeholder="Début"
                                           onchange="updateDeadline(this)">
                                    <input type="date" class="deadline-end" value="${endDate}" 
                                           style="border:1px solid #ddd;padding:2px;font-size:10px;width:48%;"
                                           placeholder="Fin"
                                           onchange="updateDeadline(this)">
                                  </div>
                                  <div class="days-remaining ${daysClass}" style="font-size:11px;text-align:center;font-weight:bold;">${daysText}</div>
                                </div>
                              `;
                              cellPromises.push(Promise.resolve(inputHtml));
                            } else if(c.type === 'user') {
                              const promise = fetch('?users_list')
                                .then(r=>r.json())
                                .then(users=>{
                                  let selectHtml = `<select class="cell-select user-select" data-task="${t.id}" data-column="${c.id}" 
                                                           style="border:none;background:transparent;width:100%;padding:2px;"
                                                           onchange="saveCellValue(this)">
                                                     <option value="">-- Non assigné --</option>`;
                                  users.forEach(user=>{
                                    const selected = cellValue == user.id ? 'selected' : '';
                                    selectHtml += `<option value="${user.id}" ${selected}>${user.name}</option>`;
                                  });
                                  selectHtml += '</select>';
                                  
                                  // Si un utilisateur est sélectionné, on affiche avec avatar
                                  if (cellValue) {
                                    const selectedUser = users.find(u => u.id == cellValue);
                                    if (selectedUser) {
                                      const initials = selectedUser.name.split(' ').map(n => n[0]).join('').substr(0, 2).toUpperCase();
                                      return `
                                        <div class="user-cell" data-task="${t.id}" data-column="${c.id}" style="cursor:pointer;" onclick="openUserSelector(this)">
                                          <div class="user-avatar" title="${selectedUser.name}">${initials}</div>
                                          <span>${selectedUser.name}</span>
                                          ${selectHtml.replace('style="', 'style="display:none;')}
                                        </div>
                                      `;
                                    }
                                  }
                                  
                                  return `<div class="user-cell unassigned" data-task="${t.id}" data-column="${c.id}" style="cursor:pointer;" onclick="openUserSelector(this)">${selectHtml}</div>`;
                                });
                              cellPromises.push(promise);
                            } else if(c.type === 'date') {
                              const inputHtml = `<input type="date" class="cell-input cell-date" 
                                                        data-task="${t.id}" 
                                                        data-column="${c.id}" 
                                                        value="${cellValue}" 
                                                        style="border:none;background:transparent;width:100%;padding:2px;cursor:pointer;"
                                                        onblur="saveCellValue(this)"
                                                        onchange="saveCellValue(this)">`;
                              cellPromises.push(Promise.resolve(inputHtml));
                            } else {
                              const inputHtml = `<input type="text" class="cell-input" 
                                                        data-task="${t.id}" 
                                                        data-column="${c.id}" 
                                                        value="${cellValue}" 
                                                        style="border:none;background:transparent;width:100%;padding:2px;"
                                                        onblur="saveCellValue(this)"
                                                        onkeydown="if(event.key==='Enter') saveCellValue(this)">`;
                              cellPromises.push(Promise.resolve(inputHtml));
                            }
                          });
                          
                          Promise.all(cellPromises).then(cellsHtml=>{
                            cellsHtml.forEach(cellHtml=>{
                              tds += `<td style="border:1px solid #ddd;padding:4px;">${cellHtml}</td>`;
                            });
                            tds += `<td style="border:1px solid #ddd;padding:4px;"></td>`;
                            const $taskRow = $(`<tr data-id="${t.id}" style="cursor:pointer;">${tds}</tr>`);
                            $grp.find('tbody').append($taskRow);
                            
                            $taskRow.find('td:nth-child(3)').click(function(e) {
                              if ($(e.target).is('button')) return;
                              
                              const taskName = $(this).text();
                              const groupName = $grp.find('.group-label').text();
                              openTaskDetail(t.id, taskName, groupName);
                            });
                            
                            $grp.find('select.cell-select').each(function(){
                              applySelectColor($(this));
                            });
                          });
                        });
                    });
                    initTaskSortable();
                  });
              });
          });

          initGroupSortable();

          // Gestionnaires d'événements pour les colonnes, groupes et tâches
          attachEventHandlers(wid);
        });
  }

  // Fonction globale pour gérer les événements des éléments
  function attachEventHandlers(wid) {
    // Ajout de colonnes
    $('#group-list').off('click','.add-column-btn').on('click','.add-column-btn',function(e){
      e.stopPropagation();
      const gid = $(this).data('gid');
      const lbl = prompt('Nom de la colonne :');
      if(!lbl) return;
      
      const typeModal = $(`
        <div id="type-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1001;display:flex;align-items:center;justify-content:center;">
          <div style="background:white;padding:25px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.3);min-width:350px;">
            <h3 style="margin:0 0 20px 0;text-align:center;color:#333;">Choisir le type de colonne</h3>
            <div style="display:flex;flex-direction:column;gap:12px;margin:20px 0;">
              <button class="type-choice" data-type="text" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                <span style="font-size:20px;">📝</span>
                <div style="text-align:left;">
                  <div style="font-weight:bold;">Texte</div>
                  <div style="font-size:12px;color:#666;">Saisie libre de texte</div>
                </div>
              </button>
              <button class="type-choice" data-type="number" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                <span style="font-size:20px;">🔢</span>
                <div style="text-align:left;">
                  <div style="font-weight:bold;">Nombre</div>
                  <div style="font-size:12px;color:#666;">Saisie numérique uniquement</div>
                </div>
              </button>
              <button class="type-choice" data-type="select" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                <span style="font-size:20px;">📋</span>
                <div style="text-align:left;">
                  <div style="font-weight:bold;">Liste déroulante</div>
                  <div style="font-size:12px;color:#666;">Options prédéfinies avec couleurs</div>
                </div>
              </button>
              <button class="type-choice" data-type="tags" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                <span style="font-size:20px;">🏷️</span>
                <div style="text-align:left;">
                  <div style="font-weight:bold;">Étiquettes</div>
                  <div style="font-size:12px;color:#666;">Tags multiples</div>
                </div>
              </button>
              <button class="type-choice" data-type="date" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                <span style="font-size:20px;">📅</span>
                <div style="text-align:left;">
                  <div style="font-weight:bold;">Date</div>
                  <div style="font-size:12px;color:#666;">Sélecteur de calendrier</div>
                </div>
              </button>
              <button class="type-choice" data-type="deadline" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                <span style="font-size:20px;">⏰</span>
                <div style="text-align:left;">
                  <div style="font-weight:bold;">Échéance</div>
                  <div style="font-size:12px;color:#666;">Période avec décompte des jours</div>
                </div>
              </button>
              <button class="type-choice" data-type="user" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                <span style="font-size:20px;">👤</span>
                <div style="text-align:left;">
                  <div style="font-weight:bold;">Assigné à</div>
                  <div style="font-size:12px;color:#666;">Choisir un utilisateur</div>
                </div>
              </button>
            </div>
            <div style="text-align:center;margin-top:20px;">
              <button id="cancel-type" style="padding:10px 20px;background:#ccc;border:none;cursor:pointer;border-radius:6px;color:#666;">Annuler</button>
            </div>
          </div>
        </div>
      `);
      
      $('body').append(typeModal);
      
      $('.type-choice').hover(
        function() {
          $(this).css({
            'border-color': '#007cba',
            'background': '#f0f8ff',
            'transform': 'translateY(-2px)',
            'box-shadow': '0 4px 12px rgba(0,124,186,0.15)'
          });
        },
        function() {
          $(this).css({
            'border-color': '#e0e0e0',
            'background': '#f9f9f9',
            'transform': 'translateY(0)',
            'box-shadow': 'none'
          });
        }
      );
      
      $('.type-choice').click(function(){
        const type = $(this).data('type');
        const fd = new FormData();
        fd.append('add_column_group_id',gid);
        fd.append('column_label',lbl);
        fd.append('column_type',type);
        fd.append('token',token);
        fetch('',{method:'POST',body:fd}).then(()=>{
          typeModal.remove();
          loadGroups(wid);
        });
      });
      
      $('#cancel-type').click(function(){
        typeModal.remove();
      });
      
      typeModal.click(function(e){
        if(e.target === typeModal[0]) {
          typeModal.remove();
        }
      });
    });

    // Toggle groupes
    $('.group-toggle').off('click').on('click',function(e){
      e.stopPropagation();
      const $g    = $(this).closest('.group');
      const $body = $g.find('.group-body');
      $body.toggle();
      $(this).text($body.is(':visible') ? '▼' : '►');
      const newState = $body.is(':visible') ? 0 : 1;
      const fd = new FormData();
      fd.append('toggle_group_id', $g.data('id'));
      fd.append('collapsed', newState);
      fd.append('token', token);
      fetch('',{method:'POST',body:fd});
    });

    // Autres gestionnaires d'événements
    $('#group-list')
      .off('click','.rename-group').on('click','.rename-group',function(){
        const $g=$(this).closest('.group');
        const gid=$g.data('id');
        const old=$g.find('.group-label').text();
        const nw=prompt('Nouveau nom du groupe :',old);
        if(!nw) return;
        const fd=new FormData();
        fd.append('rename_group_id',gid);
        fd.append('group_label',nw);
        fd.append('token',token);
        fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
      })
      .off('click','.delete-group').on('click','.delete-group',function(){
        const $g=$(this).closest('.group');
        const gid=$g.data('id');
        if(!confirm('Supprimer ce groupe ?')) return;
        const fd=new FormData();
        fd.append('delete_group_id',gid);
        fd.append('token',token);
        fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
      })
      .off('click','.add-row-btn').on('click','.add-row-btn',function(){
        const gid=$(this).closest('.group').data('id');
        const lbl=prompt('Nom de la tâche :');
        if(!lbl) return;
        const fd=new FormData();
        fd.append('add_task_group_id',gid);
        fd.append('task_label',lbl);
        fd.append('token',token);
        fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
      })
      .off('click','.delete-task-row').on('click','.delete-task-row',function(e){
        e.stopPropagation();
        const $tr = $(this).closest('tr');
        const tid = $tr.data('id');
        if(!confirm('Supprimer cette tâche ?')) return;
        const fd=new FormData();
        fd.append('delete_task_id',tid);
        fd.append('token',token);
        fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
      })
      .off('click','.rename-task-row').on('click','.rename-task-row',function(e){
        e.stopPropagation();
        const $tr = $(this).closest('tr');
        const tid = $tr.data('id');
        const old = $tr.find('td:nth-child(3)').text();
        const nw = prompt('Modifier le nom de la tâche :', old);
        if(!nw) return;
        const fd=new FormData();
        fd.append('rename_task_id',tid);
        fd.append('rename_task_label',nw);
        fd.append('token',token);
        fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
      })
      .off('click','.rename-column-btn').on('click','.rename-column-btn',function(e){
        e.stopPropagation();
        const cid = $(this).data('cid');
        const old = $(this).closest('.column-menu').siblings('.column-label').text();
        const nw = prompt('Nouveau nom de la colonne :', old);
        if(!nw) return;
        const fd = new FormData();
        fd.append('rename_column_id', cid);
        fd.append('rename_column_label', nw);
        fd.append('token', token);
        fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
      })
      .off('click','.delete-column-btn').on('click','.delete-column-btn',function(e){
        e.stopPropagation();
        const cid = $(this).data('cid');
        if(!confirm('Supprimer cette colonne ?')) return;
        const fd = new FormData();
        fd.append('delete_column_id', cid);
        fd.append('token', token);
        fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
      })
      .off('click','.manage-options-btn').on('click','.manage-options-btn',function(e){
        e.stopPropagation();
        const cid = $(this).data('cid');
        
        // Gestionnaire des options (code très long, abrégé ici)
        // Ce serait idéal de déplacer cette partie vers un fichier séparé
        manageColumnOptions(cid, token, () => loadGroups(wid));
      })
      .off('click','.column-menu-btn').on('click','.column-menu-btn',function(e){
        e.stopPropagation();
        $('.column-menu').hide();
        $(this).siblings('.column-menu').toggle();
      })
      .off('click','.column-menu').on('click','.column-menu',function(e){
        e.stopPropagation();
      });
  }

  // Fonction pour gérer les options de colonnes - Version avec sélecteur de couleurs universel
  function manageColumnOptions(cid, token, onComplete) {
    fetch(`?column_options=${cid}`)
      .then(r=>r.json())
      .then(options=>{
        const optionsModal = $(`
          <div id="options-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;">
            <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);min-width:500px;max-height:80vh;overflow-y:auto;">
              <h3>Gérer les options</h3>
              
              <div id="options-list" style="margin:15px 0;max-height:300px;overflow-y:auto;">
                ${options.map(opt => `
                  <div class="option-item" data-option-id="${opt.id}" style="display:flex;align-items:center;gap:10px;margin-bottom:10px;padding:10px;border:1px solid #ddd;border-radius:4px;">
                    <div class="color-preview" style="width:20px;height:20px;border-radius:4px;background:${opt.color || '#87CEEB'};border:1px solid #ccc;cursor:pointer;" title="Cliquer pour changer la couleur"></div>
                    <span class="option-label-display" style="flex:1;padding:4px;">${opt.label}</span>
                    <button class="edit-option-btn" data-option-id="${opt.id}" style="padding:4px 8px;background:#007cba;color:white;border:none;cursor:pointer;border-radius:3px;">✎</button>
                    <button class="delete-option-btn" data-option-id="${opt.id}" style="padding:4px 8px;background:#dc3545;color:white;border:none;cursor:pointer;border-radius:3px;">✖</button>
                  </div>
                `).join('')}
              </div>
              
              <div style="border-top:1px solid #ddd;padding-top:15px;margin-top:15px;">
                <h4>Ajouter une nouvelle option</h4>
                <div style="display:flex;gap:10px;align-items:center;">
                  <input type="text" id="new-option-label" placeholder="Label" style="flex:1;padding:6px;border:1px solid #ddd;border-radius:3px;">
                  <div id="new-option-color-preview" style="width:30px;height:30px;border-radius:4px;background:#87CEEB;border:1px solid #ccc;cursor:pointer;" title="Cliquer pour choisir une couleur"></div>
                  <button id="add-option-btn" style="padding:6px 12px;background:#007cba;color:white;border:none;cursor:pointer;border-radius:3px;">Ajouter</button>
                </div>
              </div>
              
              <div style="margin-top:20px;text-align:right;">
                <button id="close-options" style="padding:8px 16px;background:#ccc;border:none;cursor:pointer;border-radius:4px;">Fermer</button>
              </div>
            </div>
          </div>
        `);
        
        $('body').append(optionsModal);
        
        let selectedColor = '#87CEEB'; // Couleur par défaut
        
        // Fonction pour créer le sélecteur de couleurs
        function createColorPicker(currentColor, callback) {
          const colorModal = $(`
            <div id="color-picker-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:1001;display:flex;align-items:center;justify-content:center;">
              <div style="background:white;padding:25px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.3);min-width:400px;">
                <h4 style="margin:0 0 15px 0;">Choisir une couleur</h4>
                
                <div style="margin-bottom:20px;">
                  <h5 style="margin-bottom:10px;">Couleurs prédéfinies :</h5>
                  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;">
                    ${['#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#FF8000', '#00FFFF', '#800080', '#008000', '#FFC0CB'].map(color => `
                      <div class="preset-color" data-color="${color}" style="width:40px;height:40px;background:${color};border:3px solid ${currentColor === color ? '#000' : '#ccc'};cursor:pointer;border-radius:6px;transition:all 0.2s;"></div>
                    `).join('')}
                  </div>
                </div>
                
                <div style="margin-bottom:20px;">
                  <h5 style="margin-bottom:10px;">Couleur personnalisée :</h5>
                  <input type="color" id="custom-color-picker" value="${currentColor}" style="width:60px;height:40px;border:none;cursor:pointer;border-radius:4px;">
                  <span id="color-display" style="margin-left:10px;padding:8px 15px;background:${currentColor};color:white;border-radius:4px;font-weight:bold;text-shadow:1px 1px 1px rgba(0,0,0,0.5);">${currentColor}</span>
                </div>
                
                <div style="text-align:right;display:flex;gap:10px;justify-content:flex-end;">
                  <button id="apply-color" style="padding:8px 16px;background:#007cba;color:white;border:none;cursor:pointer;border-radius:4px;">Appliquer</button>
                  <button id="cancel-color" style="padding:8px 16px;background:#ccc;border:none;cursor:pointer;border-radius:4px;">Annuler</button>
                </div>
              </div>
            </div>
          `);
          
          $('body').append(colorModal);
          
          let tempColor = currentColor;
          
          // Couleurs prédéfinies
          $('.preset-color').hover(
            function() { $(this).css('transform', 'scale(1.1)'); },
            function() { $(this).css('transform', 'scale(1)'); }
          ).click(function(){
            $('.preset-color').css('border-color', '#ccc');
            $(this).css('border-color', '#000');
            tempColor = $(this).data('color');
            $('#custom-color-picker').val(tempColor);
            $('#color-display').css('background', tempColor).text(tempColor);
          });
          
          // Couleur personnalisée
          $('#custom-color-picker').on('input', function(){
            tempColor = $(this).val();
            $('#color-display').css('background', tempColor).text(tempColor);
            $('.preset-color').css('border-color', '#ccc');
            // Mettre en évidence si c'est une couleur prédéfinie
            $('.preset-color').each(function(){
              if($(this).data('color').toLowerCase() === tempColor.toLowerCase()) {
                $(this).css('border-color', '#000');
              }
            });
          });
          
          $('#apply-color').click(function(){
            callback(tempColor);
            colorModal.remove();
          });
          
          $('#cancel-color').click(function(){
            colorModal.remove();
          });
          
          colorModal.click(function(e){
            if(e.target === colorModal[0]) {
              colorModal.remove();
            }
          });
        }
        
        // Conversion RGB vers HEX
        function rgbToHex(rgb) {
          if (rgb.indexOf('#') === 0) return rgb;
          const result = rgb.match(/\d+/g);
          if (!result) return '#87CEEB';
          return '#' + ((1 << 24) + (parseInt(result[0]) << 16) + (parseInt(result[1]) << 8) + parseInt(result[2])).toString(16).slice(1);
        }
        
        // Sélecteur de couleur pour nouvelle option
        $('#new-option-color-preview').click(function(){
          createColorPicker(selectedColor, function(newColor){
            selectedColor = newColor;
            $('#new-option-color-preview').css('background', newColor);
          });
        });
        
        // Sélecteur de couleur pour options existantes
        $(document).on('click', '.color-preview', function(){
          const optionId = $(this).closest('.option-item').data('option-id');
          const currentColor = rgbToHex($(this).css('background-color'));
          const $preview = $(this);
          
          createColorPicker(currentColor, function(newColor){
            $preview.css('background', newColor);
            
            // Sauvegarder la couleur
            const fd = new FormData();
            fd.append('update_option_color', optionId);
            fd.append('option_color', newColor);
            fd.append('token', token);
            
            fetch('', {method: 'POST', body: fd})
              .then(() => console.log('Couleur mise à jour'));
          });
        });
        
        // Modifier une option (bouton crayon) - Gestionnaire délégué
        $('#options-list').off('click', '.edit-option-btn').on('click', '.edit-option-btn', function(e){
          e.preventDefault();
          e.stopPropagation();
          
          const optionId = $(this).data('option-id');
          const $item = $(this).closest('.option-item');
          const $labelDisplay = $item.find('.option-label-display');
          const currentLabel = $labelDisplay.text();
          
          const newLabel = prompt('Nouveau nom de l\'option :', currentLabel);
          
          if(!newLabel || newLabel === currentLabel) return;
          
          const fd = new FormData();
          fd.append('rename_option_id', optionId);
          fd.append('rename_option_label', newLabel);
          fd.append('token', token);
          
          fetch('', {method: 'POST', body: fd})
            .then(() => {
              $labelDisplay.text(newLabel);
              console.log('Option renommée');
            })
            .catch(e => console.log('Erreur lors du renommage:', e));
        });
        
        // Supprimer une option - Gestionnaire délégué
        $('#options-list').off('click', '.delete-option-btn').on('click', '.delete-option-btn', function(e){
          e.preventDefault();
          e.stopPropagation();
          
          const optionId = $(this).data('option-id');
          const $button = $(this);
          
          if(!confirm('Supprimer cette option ?')) return;
          
          const fd = new FormData();
          fd.append('delete_option_id', optionId);
          fd.append('token', token);
          
          fetch('', {method: 'POST', body: fd})
            .then(() => {
              $button.closest('.option-item').remove();
            })
            .catch(e => console.error('Erreur lors de la suppression:', e));
        });
        
        // Ajouter une nouvelle option
        $('#add-option-btn').click(function(){
          const label = $('#new-option-label').val().trim();
          
          if(!label) {
            alert('Veuillez saisir un label');
            return;
          }
          
          const fd = new FormData();
          fd.append('add_option_column_id', cid);
          fd.append('option_label', label);
          fd.append('option_color', selectedColor);
          fd.append('token', token);
          
          fetch('', {method: 'POST', body: fd})
            .then(() => {
              // Ajouter l'option à la liste dans le modal
              const tempId = Date.now();
              const newOption = $(`
                <div class="option-item" data-option-id="temp_${tempId}" style="display:flex;align-items:center;gap:10px;margin-bottom:10px;padding:10px;border:1px solid #ddd;border-radius:4px;">
                  <div class="color-preview" style="width:20px;height:20px;border-radius:4px;background:${selectedColor};border:1px solid #ccc;cursor:pointer;" title="Cliquer pour changer la couleur"></div>
                  <span class="option-label-display" style="flex:1;padding:4px;">${label}</span>
                  <button class="edit-option-btn" data-option-id="temp_${tempId}" style="padding:4px 8px;background:#007cba;color:white;border:none;cursor:pointer;border-radius:3px;">✎</button>
                  <button class="delete-option-btn" data-option-id="temp_${tempId}" style="padding:4px 8px;background:#dc3545;color:white;border:none;cursor:pointer;border-radius:3px;">✖</button>
                </div>
              `);
              $('#options-list').append(newOption);
              
              // Les événements sont maintenant gérés par délégation, plus besoin d'attacher manuellement
              
              // Réinitialiser les champs
              $('#new-option-label').val('');
              selectedColor = '#87CEEB';
              $('#new-option-color-preview').css('background', selectedColor);
            })
            .catch(e => console.error('Erreur lors de l\'ajout:', e));
        });
        
        // Fermer le modal
        $('#close-options').click(function(){
          optionsModal.remove();
          if(onComplete) onComplete();
        });
        
        // Fermer en cliquant à l'extérieur
        optionsModal.click(function(e){
          if(e.target === optionsModal[0]) {
            optionsModal.remove();
            if(onComplete) onComplete();
          }
        });
      });
  }

});
