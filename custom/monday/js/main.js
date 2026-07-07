$(function(){
  $('.side-nav .vmenu').prepend(window.leftmenu || '');
  const token = window.formtoken;
  const userId = window.userId;
  const mondayConfig = window.mondayConfig || {};
  
  // State pour gérer les tâches collapsées
  const taskCollapseState = new Set();
  const clientNeedCandidateState = new Set();
  let currentWorkspaceLabel = '';
  
  // Cache pour les données fréquemment utilisées
  const dataCache = {
    users: null,
    columnOptions: {}
  };

  function normalizeKpiLabel(label) {
    return String(label || '')
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9]+/g, "");
  }

  function isClientNeedWorkspaceLabel(label) {
    const normalizedWorkspace = normalizeKpiLabel(label);
    const configuredLabels = Array.isArray(mondayConfig.clientNeedWorkspaceLabels)
      ? mondayConfig.clientNeedWorkspaceLabels
      : [];

    return configuredLabels.includes(normalizedWorkspace);
  }

  // Pré-charger les utilisateurs une seule fois au démarrage
  fetch('?users_list')
    .then(r=>r.json())
    .then(users => {
      dataCache.users = users;
      console.log('Utilisateurs pré-chargés en cache');
    })
    .catch(err => console.error('Erreur pré-chargement utilisateurs:', err));
  
  // Pré-charger TOUTES les options de colonnes au démarrage
  fetch('?all_column_options')
    .then(r=>r.json())
    .then(allOptions => {
      dataCache.columnOptions = allOptions;
      console.log('Options colonnes pré-chargées en cache');
    })
    .catch(err => console.error('Erreur pré-chargement options colonnes:', err));

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
        const newItem = `<li class="workspace-item" data-id="${Number(data.id)}">${escapeHtml(data.label)}</li>`;
        $('#workspace-list').append(newItem);
        
        newWorkspaceInput.val('');
        
        console.log('Espace ajouté avec succès:', data);
      })
      .catch(error => {
        console.error('Erreur lors de l\'ajout de l\'espace:', error);
        const newId = Date.now();
        const newItem = `<li class="workspace-item" data-id="${newId}">${escapeHtml(workspaceName)}</li>`;
        $('#workspace-list').append(newItem);
        newWorkspaceInput.val('');
        
        setTimeout(() => location.reload(), 500);
      });
    }
  });

  let currentTaskId = null;
  let currentTaskColumnLabel = 'tâche';

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
    
    // Utiliser le cache ou fetcher
    const getUsersPromise = dataCache.users 
      ? Promise.resolve(dataCache.users)
      : fetch('?users_list').then(r=>r.json()).then(users => {
          dataCache.users = users;
          return users;
        });
    
    getUsersPromise.then(users=>{
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
    
    const getOptionsPromise = dataCache.columnOptions[columnId]
      ? Promise.resolve(dataCache.columnOptions[columnId])
      : fetch(`?column_options=${columnId}`)
          .then(r=>r.json())
          .then(options => {
            dataCache.columnOptions[columnId] = options;
            return options;
          });
    
    getOptionsPromise.then(options=>{
      const selectedTags = [];
      $cell.find('.tag-item').each(function(){
        const tagId = parseInt($(this).data('tag-id'));
        if(tagId && !selectedTags.includes(tagId)) {
          selectedTags.push(tagId);
        }
      });
        
        console.log('Tags actuellement sélectionnés:', selectedTags);
        
        const modal = $(`
          <div id="tags-modal" class="custom-popup-overlay show">
            <div class="custom-popup">
              <div class="custom-popup-header">
                <h3 class="custom-popup-title">Sélectionner des étiquettes</h3>
              </div>
              <div class="custom-popup-content">
                <div id="available-tags" style="margin:15px 0;">
                  ${options.map(opt => {
                    const isSelected = selectedTags.includes(parseInt(opt.id));
                    console.log(`Option ${opt.label} (ID: ${opt.id}) - Sélectionnée: ${isSelected}`); // Debug
                    return `
                      <div class="tag-option ${isSelected ? 'selected' : ''}" data-tag-id="${opt.id}" style="display:inline-block;margin:5px;padding:8px 16px;background:${opt.color || '#87CEEB'};color:white;border-radius:20px;cursor:pointer;border:3px solid ${isSelected ? '#0073ea' : 'transparent'};font-weight:500;font-size:14px;transition:all 0.2s ease;">
                        ${opt.label}
                      </div>
                    `;
                  }).join('')}
                </div>
                
                ${options.length === 0 ? '<p style="text-align:center;color:#666;font-style:italic;margin:20px 0;">Aucune étiquette disponible. Utilisez "Gérer options" dans le menu de la colonne pour en créer.</p>' : ''}
                
                <div class="custom-popup-buttons">
                  <button id="save-tags" class="custom-popup-btn custom-popup-btn-primary">Sauvegarder</button>
                  <button id="cancel-tags" class="custom-popup-btn custom-popup-btn-secondary">Annuler</button>
                </div>
              </div>
            </div>
          </div>
        `);
        
        $('body').append(modal);
        
        $('.tag-option').click(function(){
          $(this).toggleClass('selected');
          if($(this).hasClass('selected')) {
            $(this).css('border', '3px solid #0073ea');
            $(this).css('transform', 'scale(1.05)');
          } else {
            $(this).css('border', '3px solid transparent');
            $(this).css('transform', 'scale(1)');
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
            
            let tagsHtml = `
              <div class="selected-tags" style="display:flex;flex-wrap:wrap;gap:3px;margin-bottom:5px;">
            `;
            
            const getOptionsPromise = dataCache.columnOptions[columnId]
              ? Promise.resolve(dataCache.columnOptions[columnId])
              : fetch(`?column_options=${columnId}`)
                  .then(r=>r.json())
                  .then(allOptions => {
                    dataCache.columnOptions[columnId] = allOptions;
                    return allOptions;
                  });
            
            getOptionsPromise.then(allOptions=>{
              selectedTagIds.forEach(tagId => {
                const tag = allOptions.find(opt => parseInt(opt.id) === tagId);
                if(tag) {
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
                `;
                
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
          
          const textColor = getContrastColor(color);
          $select.css('color', textColor);
          
          $select.css({
            'border': 'none',
            'padding': '4px 8px',
            'border-radius': '4px',
            'font-weight': 'bold'
          });
          return;
        }
      }
    }
    
    $select.css({
      'background-color': 'transparent',
      'color': 'inherit',
      'border': '1px solid #ddd',
      'padding': '2px',
      'border-radius': '0',
      'font-weight': 'normal'
    });
  }
  
  function getContrastColor(hexColor) {
    let r, g, b;
    
    if (hexColor.startsWith('#')) {
      const hex = hexColor.substring(1);
      r = parseInt(hex.substr(0, 2), 16);
      g = parseInt(hex.substr(2, 2), 16);
      b = parseInt(hex.substr(4, 2), 16);
    } else if (hexColor.startsWith('rgb')) {
      const matches = hexColor.match(/\d+/g);
      if (matches && matches.length >= 3) {
        r = parseInt(matches[0]);
        g = parseInt(matches[1]);
        b = parseInt(matches[2]);
      } else {
        return '#000000'; 
      }
    } else {
      const namedColors = {
        'red': '#FF0000',
        'green': '#008000',
        'blue': '#0000FF',
        'yellow': '#FFFF00',
        'orange': '#FFA500',
        'purple': '#800080',
        'pink': '#FFC0CB',
        'brown': '#A52A2A',
        'gray': '#808080',
        'black': '#000000',
        'white': '#FFFFFF'
      };
      
      if (namedColors[hexColor.toLowerCase()]) {
        return getContrastColor(namedColors[hexColor.toLowerCase()]);
      }
      
      return '#000000';
    }
    
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    
    return luminance > 0.5 ? '#000000' : '#FFFFFF';
  }

  window.toggleTaskCompletion = function(taskId, isChecked) {
    const fd = new FormData();
    fd.append('toggle_task_completion', taskId);
    fd.append('is_completed', isChecked ? 1 : 0);
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd})
      .then(r => r.text())
      .then(response => {
        if (response === 'OK') {
          // Mettre à jour l'affichage
          const $checkbox = $(`.task-completion-checkbox[data-task-id="${taskId}"]`);
          const $label = $checkbox.closest('.task-cell').find('.task-label');
          
          if (isChecked) {
            $label.css({'text-decoration': 'line-through', 'color': '#999'});
          } else {
            $label.css({'text-decoration': 'none', 'color': 'inherit'});
          }
        }
      });
  };

  window.openTaskDetail = function(taskId, taskName, groupName, taskColumnLabel) {
    currentTaskId = taskId;
    currentTaskColumnLabel = taskColumnLabel ? taskColumnLabel.toLowerCase() : 'tâche';
    
    const detailTitle = 'Détail';
    const labelText = taskColumnLabel ? `${taskColumnLabel} :` : 'Tâche :';
    
    $('#task-detail-title').text(detailTitle);
    $('#task-label-text').text(labelText);
    $('#task-name-display').text(taskName);
    $('#task-group-display').text(groupName);
    $('#task-created-display').text('Chargement...');
    
    setTimeout(() => {
      $('#task-detail-title').text(detailTitle);
      $('#task-label-text').text(labelText);
    }, 50);
    
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

  window.closeTaskDetail = function(options = {}) {
    const immediate = Boolean(options && options.immediate);
    const $panel = $('#task-detail-panel');

    if (immediate) {
      $panel.addClass('no-transition');
    }

    $panel.removeClass('open');
    currentTaskId = null;
    currentTaskColumnLabel = 'tâche';

    if (immediate && $panel.length) {
      $panel[0].offsetHeight;
      $panel.removeClass('no-transition');
    }
  };

  function loadComments(taskId) {
    fetch(`?task_comments=${taskId}`)
      .then(r => r.json())
      .then(comments => {
        const $commentsList = $('#comments-list');
        $commentsList.empty();
        
        if (comments.length === 0) {
          $commentsList.append(`
            <div class="no-comments" style="text-align:center;color:#666;font-style:italic;padding:20px;">
              Aucun commentaire
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
          
          const fontFamily = comment.font_family || 'Arial';
          const fontSize = comment.font_size || 14;
          const fontWeight = comment.font_weight || 400;
          const fontColor = comment.font_color || '#000000';
          const commentStyle = `font-family: ${fontFamily}; font-size: ${fontSize}px; font-weight: ${fontWeight}; color: ${fontColor};`;
          
          const $comment = $(`
            <div class="comment-item" data-comment-id="${comment.id}">
              <div class="comment-header">
                <span class="comment-author">${comment.user_name}</span>
                <span class="comment-date">${formattedDate}</span>
              </div>
              <div class="comment-text" style="${commentStyle}">${comment.comment}</div>
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
              Aucun fichier
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

  function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

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

  window.viewTaskFile = function(fileId, fileName, mimeType) {
    const isImage = mimeType && mimeType.startsWith('image/');
    const isPdf = mimeType && mimeType.includes('pdf');
    
    if (isImage || isPdf) {
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
      window.open(`?download_file=${fileId}&type=task`, '_blank');
    }
  };

  window.deleteTaskFile = function(fileId) {
    CustomPopup.confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?', function(result) {
      if (!result) return;
      
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
            CustomPopup.error('Erreur lors de la suppression');
          }
        });
    });
  };

  window.deleteFile = function(fileId) {
    CustomPopup.confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?', function(result) {
      if (!result) return;
      
      const fd = new FormData();
      fd.append('delete_file_id', fileId);
      fd.append('token', token);
      
      fetch('', {method: 'POST', body: fd})
        .then(r => r.text())
        .then(response => {
          if (response === 'OK') {
            $(`.file-item[data-file-id="${fileId}"]`).remove();
          } else {
            CustomPopup.error('Erreur lors de la suppression');
          }
        });
    });
  };

  function addComment() {
    const editor = document.getElementById('new-comment-text');
    const commentText = editor.textContent.trim();
    const commentHTML = editor.innerHTML.trim();
    
    if (!commentText) {
      CustomPopup.error('Veuillez saisir un commentaire', 'Champ obligatoire');
      return;
    }
    
    if (!currentTaskId) {
      CustomPopup.error('Erreur: aucune tâche sélectionnée');
      return;
    }
    
    const fd = new FormData();
    fd.append('add_comment_task', currentTaskId);
    fd.append('comment_text', commentHTML);
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd})
      .then(response => {
        console.log('Statut de la réponse:', response.status);
        const contentType = response.headers.get('content-type');
        console.log('Type de contenu:', contentType);
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
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
        document.getElementById('new-comment-text').innerHTML = '';
        loadComments(currentTaskId);
      })
      .catch(err => {
        console.error('Erreur lors de l\'ajout du commentaire:', err);
        CustomPopup.error('Erreur lors de l\'ajout du commentaire: ' + err.message);
      });
  }

  $('#close-panel').click(closeTaskDetail);
  $('#add-comment-btn').click(addComment);

  $('#comment-bold-toggle').click(function(e) {
    e.preventDefault();
    $(this).toggleClass('active');
    document.execCommand('bold', false, null);
    $('#new-comment-text').focus();
  });

  $('#comment-italic-toggle').click(function(e) {
    e.preventDefault();
    $(this).toggleClass('active');
    document.execCommand('italic', false, null);
    $('#new-comment-text').focus();
  });

  $('#comment-color').change(function() {
    const color = $(this).val();
    document.execCommand('foreColor', false, color);
    $('#new-comment-text').focus();
  });

  $('#new-comment-text').keydown(function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
      addComment();
    }
  });

  $('#delete-task-from-panel').click(function() {
    if (!currentTaskId) {
      CustomPopup.error('Erreur: aucune tâche sélectionnée');
      return;
    }
    
    CustomPopup.confirm('Êtes-vous sûr de vouloir supprimer cette tâche ?', function(result) {
      if (!result) return;
      
      const fd = new FormData();
      fd.append('delete_task_id', currentTaskId);
      fd.append('token', token);
      
      fetch('', {method: 'POST', body: fd})
        .then(() => {
          closeTaskDetail();
          
          $(`tr[data-id="${currentTaskId}"]`).fadeOut(300, function() {
            $(this).remove();
          });
          
          const $activeWorkspace = $('.workspace-item').filter(function() {
            return $(this).css('background-color') === 'rgb(0, 124, 186)' ||
                   $(this).css('font-weight') === 'bold' ||
                   $(this).css('font-weight') === '700';
          });
          
          if ($activeWorkspace.length > 0) {
            const wsId = $activeWorkspace.data('id');
            if (wsId) {
              setTimeout(() => {
                loadGroups(wsId);
              }, 400);
            }
          }
        })
        .catch(err => {
          console.error('Erreur lors de la suppression de la tâche:', err);
          CustomPopup.error('Erreur lors de la suppression de la tâche');
        });
    });
  });

  $('#edit-task-name').click(function() {
    const currentName = $('#task-name-display').text();
    
    CustomPopup.prompt('Nouveau nom de la tâche:', function(newName) {
      if (!newName || newName === currentName) return;
      
      const fd = new FormData();
      fd.append('rename_task_id', currentTaskId);
      fd.append('rename_task_label', newName);
      fd.append('token', token);
      
      fetch('', {method: 'POST', body: fd})
        .then(() => {
          $('#task-name-display').text(newName);
          
          $(`tr[data-id="${currentTaskId}"] td:nth-child(1)`).text(newName);
          
          const $activeWorkspace = $('.workspace-item').filter(function() {
            const $this = $(this);
            return $this.css('background-color') === 'rgb(0, 124, 186)' || 
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
          CustomPopup.error('Erreur lors de la modification du nom');
        });
    }, currentName, 'Renommer la tâche');
  });

  $(document).on('click', '.edit-comment-btn', function() {
    const commentId = $(this).data('comment-id');
    const $commentItem = $(this).closest('.comment-item');
    const $commentText = $commentItem.find('.comment-text');
    const currentText = $commentText.text();
    const currentHTML = $commentText.html();
    
    const $editForm = $(`
      <div class="edit-comment-form">
        <div class="comment-formatting-toolbar">
          <button class="format-btn edit-bold-toggle" title="Gras">
            <strong>G</strong>
          </button>
          <button class="format-btn edit-italic-toggle" title="Italique">
            <em>I</em>
          </button>
          <input type="color" class="edit-comment-color" value="#000000" title="Couleur">
        </div>
        <div class="edit-comment-textarea-wrapper" contenteditable="true" class="comment-editor edit-comment-textarea" style="border:1px solid #ccc;padding:10px;min-height:80px;border-radius:4px;">${currentHTML}</div>
        <div class="edit-comment-actions">
          <button class="save-edit-btn" data-comment-id="${commentId}">Sauver</button>
          <button class="cancel-edit-btn">Annuler</button>
        </div>
      </div>
    `);
    
    $commentText.hide();
    $commentItem.find('.comment-actions').hide();
    $commentItem.append($editForm);
    
    // Event handlers pour la barre d'outils d'édition
    $editForm.find('.edit-bold-toggle').click(function(e) {
      e.preventDefault();
      $(this).toggleClass('active');
      document.execCommand('bold', false, null);
      $editForm.find('.edit-comment-textarea').focus();
    });
    
    $editForm.find('.edit-italic-toggle').click(function(e) {
      e.preventDefault();
      $(this).toggleClass('active');
      document.execCommand('italic', false, null);
      $editForm.find('.edit-comment-textarea').focus();
    });
    
    $editForm.find('.edit-comment-color').change(function() {
      const color = $(this).val();
      document.execCommand('foreColor', false, color);
      $editForm.find('.edit-comment-textarea').focus();
    });
  });

  $(document).on('click', '.save-edit-btn', function() {
    const commentId = $(this).data('comment-id');
    const $commentItem = $(this).closest('.comment-item');
    const $editForm = $commentItem.find('.edit-comment-form');
    const $editTextarea = $editForm.find('[contenteditable="true"]');
    
    // Récupérer le contenu HTML du contenteditable
    const newText = $editTextarea.html().trim();
    
    if (!newText) {
      CustomPopup.error('Le commentaire ne peut pas être vide', 'Champ obligatoire');
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
          CustomPopup.error('Erreur lors de la modification');
        }
      });
  });

  $(document).on('click', '.delete-comment-btn', function() {
    const commentId = $(this).data('comment-id');
    
    CustomPopup.confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?', function(result) {
      if (!result) return;
      
      const fd = new FormData();
      fd.append('delete_comment_id', commentId);
      fd.append('token', token);
      
      fetch('', {method: 'POST', body: fd})
        .then(r => r.text())
        .then(response => {
          if (response === 'OK') {
            loadComments(currentTaskId);
          } else {
            CustomPopup.error('Erreur lors de la suppression');
          }
        });
    });
  });

  $(document).on('click', '.cancel-edit-btn', function() {
    const $commentItem = $(this).closest('.comment-item');
    $commentItem.find('.edit-comment-form').remove();
    $commentItem.find('.comment-text').show();
    $commentItem.find('.comment-actions').show();
  });

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
      CustomPopup.error('Erreur: Aucune tâche sélectionnée');
      return;
    }
    
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
            CustomPopup.error('Erreur upload: ' + result.error);
          } else {
            loadTaskFiles(currentTaskId);
          }
        })
        .catch(err => {
          console.error('Erreur upload:', err);
          CustomPopup.error('Erreur lors de l\'upload du fichier: ' + file.name);
        });
    });
    
    $(this).val('');
  });

  function initSortable($element, options) {
    if (!$element.length || typeof $element.sortable !== 'function') {
      return;
    }

    $element.sortable(options);
    if (typeof $element.disableSelection === 'function') {
      $element.disableSelection();
    }
  }

  initSortable($('#workspace-list'), {
    cursor:'pointer',
    update(){
      const order = $('#workspace-list .workspace-item').map((_,el)=>el.dataset.id).get();
      fetch('',{method:'POST',body:new URLSearchParams({
        reorder_workspaces: JSON.stringify(order),
        token: token
      })});
    }
  });

  function initGroupSortable(){
    initSortable($('#group-list'), {
      cursor:'pointer',
      update(){
        const order = $('#group-list .group').map((_,el)=>el.dataset.id).get();
        fetch('',{method:'POST',body:new URLSearchParams({
          reorder_groups: JSON.stringify(order),
          token: token
        })});
      }
    });
  }
  
  function initTaskSortable(){
    initSortable($('.group-body > table.tasks-table > tbody.tasks-tbody'), {
      items: '> tr.task-row',
      cursor:'pointer',
      update(){
        const order = $(this).children('.task-row').map((_,tr)=>tr.dataset.id).get();
        fetch('',{method:'POST',body:new URLSearchParams({
          reorder_tasks: JSON.stringify(order),
          token: token
        })});
      }
    });
  }

  function initColumnSortable(){
    $('.group-body > table.tasks-table > thead > tr').each(function(){
      const $tr = $(this);
      const $group = $tr.closest('.group');
      const groupId = $group.data('id');
      
      initSortable($tr, {
        items: 'th:not(:first-child):not(:last-child)',
        cursor: 'move',
        axis: 'x',
        helper: 'clone',
        placeholder: 'ui-sortable-placeholder',
        tolerance: 'pointer',
        distance: 10,
        cancel: '.column-menu-btn, .column-menu',
        start: function(event, ui) {
          ui.placeholder.height(ui.item.height());
          ui.placeholder.css({
            'background': '#e3f2fd',
            'border': '2px dashed #2196f3',
            'opacity': '0.7'
          });
          
          $('.column-menu').removeClass('show').hide();
        },
        update: function(event, ui) {
          const columnOrder = [];
          $tr.find('th:not(:first-child):not(:last-child)').each(function() {
            const $span = $(this).find('.column-label');
            if ($span.length > 0) {
              const columnId = $span.data('cid');
              if (columnId) {
                columnOrder.push(parseInt(columnId));
              }
            }
          });
          
          if (columnOrder.length > 0) {
            fetch('', {
              method: 'POST',
              body: new URLSearchParams({
                reorder_columns: JSON.stringify(columnOrder),
                token: token
              })
            }).then(() => {
              setTimeout(() => {
                const workspaceId = $('.workspace-item.active').data('id');
                if (workspaceId) {
                  loadGroups(workspaceId);
                }
              }, 200);
            }).catch(error => {
              console.error('Erreur lors de la réorganisation des colonnes:', error);
            });
          }
        }
      });
    });
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function highlightEscapedText(value, regex) {
    const text = String(value ?? '');
    let lastIndex = 0;
    let highlighted = '';

    text.replace(regex, (match, ...args) => {
      const offset = args[args.length - 2];
      highlighted += escapeHtml(text.slice(lastIndex, offset));
      highlighted += `<span class="search-highlight">${escapeHtml(match)}</span>`;
      lastIndex = offset + match.length;
      return match;
    });

    return highlighted + escapeHtml(text.slice(lastIndex));
  }

  function getClientNeedCandidateRows(taskId, candidatesByNeed) {
    const key = String(taskId);
    if (!Object.prototype.hasOwnProperty.call(candidatesByNeed, key)) {
      return null;
    }
    return Array.isArray(candidatesByNeed[key]) ? candidatesByNeed[key] : [];
  }

  function isClientNeedCandidatesExpanded(taskId) {
    return clientNeedCandidateState.has(String(taskId));
  }

  function renderClientNeedCandidatesToggle(taskId, count) {
    const expanded = isClientNeedCandidatesExpanded(taskId);
    const panelId = `client-need-candidates-${Number(taskId)}`;
    return `
      <button class="candidates-toggle" type="button" data-need-id="${Number(taskId)}" aria-expanded="${expanded ? 'true' : 'false'}" aria-controls="${panelId}">
        <span class="candidates-caret" aria-hidden="true">${expanded ? '▼' : '▶'}</span>
        <span>Candidatures</span>
        <span class="count">${count}</span>
      </button>`;
  }

  function renderClientNeedCandidatesPanel(taskId, candidates) {
    const expanded = isClientNeedCandidatesExpanded(taskId);
    const panelId = `client-need-candidates-${Number(taskId)}`;
    const candidateRows = (candidates || []).map(candidate => `
      <tr>
        <td>
          <button class="candidate-detail-link" type="button" data-candidate-id="${Number(candidate.id)}" data-candidate-name="${escapeHtml(candidate.name)}">
            ${escapeHtml(candidate.name)}
          </button>
        </td>
        <td>${escapeHtml(candidate.date_envoie_client || '')}</td>
        <td>${escapeHtml(candidate.action_client || '')}</td>
      </tr>
    `).join('');

    return `
      <div id="${panelId}" class="candidates-panel" data-need-id="${Number(taskId)}"${expanded ? '' : ' hidden'}>
        <table class="candidates-table">
          <colgroup>
            <col style="width: 46.666%">
            <col style="width: 26.667%">
            <col style="width: 26.667%">
          </colgroup>
          <thead>
            <tr>
              <th>Prénom et nom</th>
              <th>Date d'envoi</th>
              <th>Action client</th>
            </tr>
          </thead>
          <tbody>${candidateRows || '<tr><td colspan="3" class="candidates-empty">Aucune candidature</td></tr>'}</tbody>
        </table>
      </div>
    `;
  }

  function setClientNeedCandidatesExpanded($button, expanded) {
    const needId = String($button.data('need-id'));
    const $panel = $button.closest('.task-cell').find(`.candidates-panel[data-need-id="${needId}"]`);

    $panel.prop('hidden', !expanded);
    $button.attr('aria-expanded', expanded ? 'true' : 'false');
    $button.find('.candidates-caret').text(expanded ? '▼' : '▶');

    if (expanded) {
      clientNeedCandidateState.add(needId);
    } else {
      clientNeedCandidateState.delete(needId);
    }
  }

  function buildKpiQuery() {
    const params = new URLSearchParams();
    params.set('kpi_recruitment', '1');
    params.set('token', token);

    const year = $('#kpi-year').val();
    const startDate = $('#kpi-start-date').val();
    const endDate = $('#kpi-end-date').val();
    const client = $('#kpi-client').val();

    if (year) {
      params.set('year', year);
    } else {
      if (startDate) params.set('start_date', startDate);
      if (endDate) params.set('end_date', endDate);
    }
    if (client) params.set('client', client);

    return params.toString();
  }

  function formatKpiPercent(value) {
    return `${Number(value || 0).toLocaleString('fr-FR', { maximumFractionDigits: 1 })}%`;
  }

  function buildPieGradient(series) {
    if (!series.length) {
      return '#eef1f4';
    }

    let cursor = 0;
    const stops = [];
    series.forEach(item => {
      const start = cursor;
      const end = cursor + Number(item.percentage || 0);
      stops.push(`${item.color || '#cccccc'} ${start}% ${end}%`);
      cursor = end;
    });

    if (cursor < 100) {
      stops.push(`#f2f4f7 ${cursor}% 100%`);
    }

    return `conic-gradient(${stops.join(', ')})`;
  }

  function renderKpiChart(metric) {
    const series = metric.series || [];
    const legend = series.map(item => `
      <div class="kpi-legend-row">
        <span class="kpi-legend-color" style="background:${escapeHtml(item.color || '#cccccc')}"></span>
        <span class="kpi-legend-label">${escapeHtml(item.label)}</span>
        <strong>${formatKpiPercent(item.percentage)}</strong>
        <span class="kpi-legend-count">${item.count}</span>
      </div>
    `).join('');

    const labels = series
      .filter(item => Number(item.percentage) >= 5)
      .map(item => `<span style="background:${escapeHtml(item.color || '#cccccc')}">${formatKpiPercent(item.percentage)}</span>`)
      .join('');

    return `
      <section class="kpi-card">
        <h3>${escapeHtml(metric.title)}</h3>
        <div class="kpi-chart-body">
          <div class="kpi-donut-wrap">
            <div class="kpi-donut" style="background:${buildPieGradient(series)}">
              <div class="kpi-donut-hole"></div>
            </div>
            <div class="kpi-donut-labels">${labels}</div>
          </div>
          <div class="kpi-legend">
            ${legend || '<div class="kpi-empty">Aucune donnée</div>'}
          </div>
        </div>
      </section>
    `;
  }

  function renderKpiBars(series) {
    return series.map(item => {
      const percentage = Number(item.percentage || 0);
      const width = Math.max(percentage, percentage > 0 ? 5 : 0);
      return `
        <div class="kpi-bar-row">
          <span class="kpi-bar-label" title="${escapeHtml(item.label)}">${escapeHtml(item.label)}</span>
          <div class="kpi-bar-track">
            <div class="kpi-bar-fill" style="width:${width}%;background:${escapeHtml(item.color || '#6b5fad')}"></div>
          </div>
          <strong title="${item.count} ligne${Number(item.count || 0) > 1 ? 's' : ''}">${formatKpiPercent(percentage)}</strong>
        </div>
      `;
    }).join('');
  }

  function renderResponseDelaySection(delay) {
    const series = delay?.series || [];
    return `
      <section class="kpi-wide-card">
        <div class="kpi-section-title">
          <h3>${escapeHtml(delay?.title || 'Délai moyen de réponse client')}</h3>
          <span>${Number(delay?.valid_rows || 0)} lignes avec deux dates valides</span>
        </div>
          <div class="kpi-delay-layout">
          <div class="kpi-stat-tile">
            <strong>${escapeHtml(delay?.average_label || 'Aucune donnée')}</strong>
            <span>Délai moyen exact</span>
          </div>
          <div class="kpi-top-bars">
            ${series.length ? renderKpiBars(series) : '<div class="kpi-empty">Aucune ligne avec date envoie client et date retour.</div>'}
          </div>
        </div>
      </section>
    `;
  }

  function renderActionCorrectiveSection(actionCorrective) {
    const series = actionCorrective?.series || [];
    return `
      <section class="kpi-wide-card">
        <div class="kpi-section-title">
          <h3>${escapeHtml(actionCorrective?.title || 'Actions correctives')}</h3>
          <span>${Number(actionCorrective?.filled || 0)} / ${Number(actionCorrective?.total || 0)} lignes renseignées</span>
        </div>
        <div class="kpi-top-bars kpi-scroll-bars">
          ${series.length ? renderKpiBars(series) : '<div class="kpi-empty">Aucune action corrective renseignée.</div>'}
        </div>
      </section>
    `;
  }

  function renderKpiExportSection() {
    return `
      <section class="kpi-export-card">
        <div class="kpi-section-title">
          <h3>Export KPI recrutement</h3>
          <span>CSV compatible Excel</span>
        </div>
        <div class="kpi-export-controls">
          <select id="kpi-export-group">
            <option value="all">Tous les tableaux</option>
          </select>
          <button id="kpi-export-btn" type="button">Exporter</button>
        </div>
      </section>
    `;
  }

  function loadKpiExportGroups() {
    const params = new URLSearchParams();
    params.set('kpi_export_groups', '1');
    params.set('token', token);

    fetch(`?${params.toString()}`)
      .then(r => r.json())
      .then(groups => {
        const options = (groups || []).map(group => `
          <option value="${group.id}">${escapeHtml(group.label)}</option>
        `).join('');
        $('#kpi-export-group').html(`<option value="all">Tous les tableaux</option>${options}`);
      })
      .catch(error => console.error('Erreur chargement exports KPI:', error));
  }

  function buildKpiExportUrl() {
    const params = new URLSearchParams();
    params.set('kpi_export_csv', $('#kpi-export-group').val() || 'all');
    params.set('token', token);
    return `?${params.toString()}`;
  }

  function loadKpiDashboard() {
    $('#kpi-results').html('<div class="kpi-loading">Chargement des KPI...</div>');

    fetch(`?${buildKpiQuery()}`)
      .then(r => r.json())
      .then(data => {
        const selectedClient = $('#kpi-client').val();
        const clients = data.clients || [];
        $('#kpi-client').html(`
          <option value="">Tous les clients</option>
          ${clients.map(client => `<option value="${escapeHtml(client)}" ${client === selectedClient ? 'selected' : ''}>${escapeHtml(client)}</option>`).join('')}
        `);

        const metricCards = (data.metrics || []).map(renderKpiChart).join('');
        $('#kpi-results').html(`
          <div class="kpi-summary">
            <strong>${data.total || 0}</strong>
            <span>lignes utilisées pour ces KPI</span>
          </div>
          <div class="kpi-grid">
            ${metricCards}
          </div>
          <div class="kpi-analytics-grid">
            ${renderResponseDelaySection(data.response_delay)}
            ${renderActionCorrectiveSection(data.action_corrective)}
          </div>
          ${renderKpiExportSection()}
        `);
        loadKpiExportGroups();
        $('#kpi-export-btn').on('click', function() {
          window.location.href = buildKpiExportUrl();
        });
      })
      .catch(error => {
        console.error('Erreur KPI:', error);
        $('#kpi-results').html('<div class="kpi-error">Impossible de charger les KPI.</div>');
      });
  }

  function showKpiDashboard() {
    $('.workspace-item').removeClass('active').css({
      'background-color': '',
      'color': '',
      'font-weight': ''
    });
    $('.workspace-kpi-entry').addClass('active');

    const currentYear = new Date().getFullYear();
    const years = [];
    for (let year = currentYear + 1; year >= currentYear - 6; year--) {
      years.push(year);
    }

    $('#main-content').html(`
      <div class="kpi-page">
        <div class="kpi-header">
          <h2>KPI recrutement</h2>
        </div>
        <div class="kpi-filters">
          <label>
            <span>Année</span>
            <select id="kpi-year">
              <option value="">Période personnalisée</option>
              ${years.map(year => `<option value="${year}">${year}</option>`).join('')}
            </select>
          </label>
          <label>
            <span>Date de début</span>
            <input type="date" id="kpi-start-date">
          </label>
          <label>
            <span>Date de fin</span>
            <input type="date" id="kpi-end-date">
          </label>
          <label>
            <span>Client</span>
            <select id="kpi-client">
              <option value="">Tous les clients</option>
            </select>
          </label>
          <button id="kpi-apply-filter">Appliquer</button>
          <button id="kpi-reset-filter" type="button">Réinitialiser</button>
        </div>
        <div id="kpi-results"></div>
      </div>
    `);

    $('#kpi-year').on('change', function() {
      const disabled = Boolean($(this).val());
      $('#kpi-start-date, #kpi-end-date').prop('disabled', disabled);
      loadKpiDashboard();
    });
    $('#kpi-client').on('change', loadKpiDashboard);
    $('#kpi-apply-filter').on('click', loadKpiDashboard);
    $('#kpi-reset-filter').on('click', function() {
      $('#kpi-year, #kpi-start-date, #kpi-end-date, #kpi-client').val('');
      $('#kpi-start-date, #kpi-end-date').prop('disabled', false);
      loadKpiDashboard();
    });

    loadKpiDashboard();
  }

  $(document).on('click', '.workspace-kpi-entry', function() {
    closeTaskDetail({ immediate: true });
    showKpiDashboard();
  });

  $(document).on('click','.workspace-item', function(){
    const wsId    = this.dataset.id;
    const wsLabel = this.textContent;
    const escapedWsLabel = escapeHtml(wsLabel);
    closeTaskDetail({ immediate: true });
    currentWorkspaceLabel = wsLabel;
    $('.workspace-kpi-entry').removeClass('active');
    
    $('.workspace-item').removeClass('active').css({
      'background-color': '',
      'color': '',
      'font-weight': ''
    });
    $(this).addClass('active').css({
      'background-color': '#007cba',
      'color': 'white',
      'font-weight': 'bold'
    });
    
    $('#main-content').html(`
      <div style="display:flex;align-items:center;gap:10px;">
        <h2 style="margin:0;cursor:pointer;">${escapedWsLabel}</h2>
        <button id="rename-btn" style="padding:2px 6px;">✎</button>
        <button id="delete-btn" style="padding:2px 6px;">✖</button>
      </div>
      <div style="display:flex;align-items:center;gap:10px;margin:1rem 0;">
        <button id="add-group-btn">+ Ajouter un groupe</button>
        <div style="position:relative;display:inline-block;">
          <input type="text" id="workspace-search" placeholder="Rechercher dans cet espace..." 
                 style="padding:6px 30px 6px 10px;border:1px solid #ccc;border-radius:4px;width:250px;">
          <button id="clear-workspace-search" style="position:absolute;right:5px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#999;font-size:16px;padding:0;width:20px;height:20px;display:none;">×</button>
        </div>
      </div>
      <div id="search-info" style="font-size:12px;color:#666;margin-bottom:10px;display:none;"></div>
      <div id="group-list"></div>
    `);

    $('#rename-btn').click(()=>{
      CustomPopup.prompt("Nouveau nom de l'espace :", function(n) {
        if(!n) return;
        const fd=new FormData(); fd.append('rename_workspace_id',wsId);
        fd.append('rename_workspace_label',n); fd.append('token',token);
        
        console.log('Renommage de l\'espace:', wsId, 'vers:', n);
        
        fetch('',{method:'POST',body:fd})
          .then(response => {
            console.log('Réponse du serveur pour renommage:', response.status);
            return response.text();
          })
          .then(responseText => {
            console.log('Contenu de la réponse:', responseText);
            
            $('#main-content h2').text(n);
            $(`.workspace-item[data-id="${wsId}"]`).text(n);
            
            console.log('Interface mise à jour avec succès');
          })
          .catch(error => {
            console.error('Erreur lors du renommage:', error);
          });
      }, wsLabel, "Renommer l'espace");
    });
    $('#delete-btn').click(()=>{
      CustomPopup.confirm('Supprimer cet espace ?', function(result) {
        if(!result) return;
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
            
            $(`.workspace-item[data-id="${wsId}"]`).remove();
            $('#main-content').html('<div style="text-align:center;padding:50px;color:#666;"><h3>Sélectionnez un espace de travail</h3><p>Choisissez un espace dans la liste de gauche pour commencer.</p></div>');
            
            console.log('Espace supprimé de l\'interface');
          })
          .catch(error => {
            console.error('Erreur lors de la suppression:', error);
          });
      });
    });
    $('#add-group-btn').click(()=>{
      CustomPopup.prompt('Nom du groupe :', function(n) {
        if(!n) return;
        const fd=new FormData(); fd.append('add_group_workspace_id',wsId);
        fd.append('group_label',n); fd.append('token',token);
      fetch('',{method:'POST',body:fd}).then(()=>{
        loadGroups(wsId);
        loadKpiExportGroups();
      });
      }, '', 'Ajouter un groupe');
    });

    if ($('#workspace-search').length) {
      $('#workspace-search').val('');
      $('#search-info').hide();
    }

    loadGroups(wsId);
  });

  function loadGroups(wid){
      fetch(`get_groups.php?wid=${wid}`)
        .then(r=>r.json()).then(groups=>{
          $('#group-list').empty();
          groups.forEach(g=>{
            fetch(`?columns_group_id=${g.id}`)
              .then(r=>r.json())
	              .then(cols=>{
                const taskColumnLabel = g.task_column_label || 'Tâche';
                const escapedTaskColumnLabel = escapeHtml(taskColumnLabel);
                const escapedGroupLabel = escapeHtml(g.label);
	                let ths = `
	                  <th style="border:1px solid #ddd;padding:4px;position:relative;">
	                    <span class="task-column-label" data-gid="${Number(g.id)}" style="cursor:pointer;" title="Cliquer pour modifier">${escapedTaskColumnLabel}</span>
	                  </th>
	                `;
	                cols.forEach(c=>{
                  const columnId = Number(c.id);
                  const escapedColumnLabel = escapeHtml(c.label);
                  const escapedColumnType = escapeHtml(c.type);
	                  ths += `<th style="border:1px solid #ddd;padding:4px;position:relative;cursor:move;" title="Glisser pour réorganiser">
	                            <span class="column-label" data-cid="${columnId}" style="cursor:pointer;">${escapedColumnLabel}<span class="column-sort-indicator" data-cid="${columnId}"></span></span>
	                            <button class="column-menu-btn" data-cid="${columnId}">⋮</button>
	                            <div class="column-menu" style="display:none;position:absolute;right:0;top:22px;z-index:10;">
	                              <button class="sort-asc-btn" data-cid="${columnId}" data-type="${escapedColumnType}">Trier croissant ↑</button>
	                              <button class="sort-desc-btn" data-cid="${columnId}" data-type="${escapedColumnType}">Trier décroissant ↓</button>
	                              <button class="rename-column-btn" data-cid="${columnId}">Renommer</button>
	                              <button class="delete-column-btn" data-cid="${columnId}">Supprimer</button>
	                              ${(c.type === 'select' || c.type === 'tags') ? `<button class="manage-options-btn" data-cid="${columnId}">Gérer options</button>` : ''}
	                            </div>
	                         </th>`;
	                });
	                ths += `<th style="border:1px solid #ddd;padding:4px;">
	                          <button class="add-column-btn" data-gid="${Number(g.id)}" style="padding:2px 6px;">+</button>
	                        </th>`;

	                const $grp = $(`
	                  <div class="group" data-id="${Number(g.id)}">
	                    <div class="group-header" style="display:flex;justify-content:space-between;padding:8px;background:#f3f3f3;">
	                      <div style="display:flex;align-items:center;gap:8px;">
	                        <span class="group-toggle">▼</span>
	                        <span class="group-label">${escapedGroupLabel}</span>
	                      </div>
                      <div>
                        <button class="rename-group">✎</button>
                        <button class="duplicate-group">⧉</button>
                        <button class="delete-group">✖</button>
                      </div>
                    </div>
                    <div class="group-body" style="padding:10px;">
                      <table class="tasks-table" style="width:100%;border-collapse:collapse;margin-bottom:8px;">
	                        <thead>
                          <tr style="background:#fafafa;">
                            ${ths}
                          </tr>
                        </thead>
                        <tbody class="tasks-tbody"></tbody>
                      </table>
	                      <button class="add-row-btn" style="padding:4px 8px;">+ Ajouter ${escapeHtml(g.task_column_label || 'tâche')}</button>
                    </div>
                  </div>
                `);

                if (g.collapsed === 1) {
                  $grp.find('.group-body').hide();
                  $grp.find('.group-toggle').text('►');
                }

                $('#group-list').append($grp);

                // Pré-charger les options des colonnes select/tags en parallèle
                const optionFetches = cols
                  .filter(c => (c.type === 'select' || c.type === 'tags') && !dataCache.columnOptions[c.id])
                  .map(c => 
                    fetch(`?column_options=${c.id}`)
                      .then(r=>r.json())
                      .then(options => {
                        dataCache.columnOptions[c.id] = options;
                        return options;
                      })
                  );

                Promise.all([
                  fetch(`?tasks_group_id_with_cells=${g.id}`).then(r=>r.json()),
                  fetch(`?client_need_candidates_group_id=${g.id}&token=${encodeURIComponent(token)}`).then(r=>r.json()).catch(() => ({enabled: false, candidates_by_need: {}}))
                ])
	                  .then(([tasks, candidatePayload])=>{
	                    const needsCandidatesEnabled = Boolean(candidatePayload && candidatePayload.enabled);
	                    const candidatesByNeed = candidatePayload?.candidates_by_need || {};
	                    const flattenNeedRows = isClientNeedWorkspaceLabel(currentWorkspaceLabel);
	                    const sortedTasks = sortTasksHierarchically(tasks);
	                    
		                    const taskPromises = sortedTasks.map(t=>{
		                      return new Promise((resolve) => {
                            const taskId = Number(t.id);
                            const parentTaskId = Number(t.parent_task_id || 0);
		                        const displayLevel = flattenNeedRows ? (Number(t.level_depth || 0) > 0 ? 1 : 0) : (t.level_depth || 0);
		                        const indentPx = displayLevel * 20;
		                        const indentStyle = `padding-left: ${4 + indentPx}px;`;

		                        // Vérifier si cette tâche a des enfants
		                        const hasChildren = !flattenNeedRows && sortedTasks.some(task => Number(task.parent_task_id || 0) === taskId);
		                        const isCollapsed = taskCollapseState.has(t.id);
		                        const collapseBtn = hasChildren
		                          ? `<button class="collapse-toggle" data-task-id="${taskId}" onclick="window.toggleCollapse(${taskId})" style="width:16px;background:none;border:none;cursor:pointer;padding:0;font-size:12px;">${isCollapsed ? '▶' : '▼'}</button>`
		                          : `<span style="width:16px;display:inline-block;"></span>`;

		                        const subtaskIndicator = !flattenNeedRows && t.level_depth > 0 ? '└─ ' : '';
	                        const isCompleted = t.is_completed ? 'checked' : '';
	                        const completedStyle = t.is_completed ? 'text-decoration: line-through; color: #999;' : '';
	                        const checkboxHtml = t.level_depth > 0 ? `<input type="checkbox" class="task-completion-checkbox" data-task-id="${taskId}" ${isCompleted} style="cursor:pointer;width:16px;height:16px;" onchange="window.toggleTaskCompletion(${taskId}, this.checked)">` : '';

	                        const needCandidates = needsCandidatesEnabled ? getClientNeedCandidateRows(taskId, candidatesByNeed) : null;
	                        const candidatesToggle = needCandidates ? renderClientNeedCandidatesToggle(taskId, needCandidates.length) : '';
	                        const candidatesPanel = needCandidates ? renderClientNeedCandidatesPanel(taskId, needCandidates) : '';

	                        let tds = `
	                          <td style="border:1px solid #ddd;${indentStyle}" class="task-cell" data-level="${t.level_depth || 0}">
	                            <div style="display: flex; align-items: center; gap: 5px;">
	                              ${collapseBtn}
	                              <span style="color: #999; font-family: monospace;">${subtaskIndicator}</span>
	                              ${checkboxHtml}
	                              <span class="task-label" style="${completedStyle}">${escapeHtml(t.label)}</span>
	                              ${candidatesToggle}
	                              <button class="add-subtask-btn" data-task-id="${taskId}" style="opacity: 0; transition: opacity 0.2s; background: none; border: none; cursor: pointer; color: #007cba; font-size: 12px;" title="Ajouter une sous-tâche">+</button>
	                            </div>
	                            ${candidatesPanel}
	                          </td>
	                        `;

	                        // Les cellules sont déjà incluses dans la réponse du nouvel endpoint
	                        const cells = t.cells || {};
	                        let cellPromises = [];
	                        cols.forEach(c=>{
	                          const cellValue = cells[c.id] || '';

	                          if(c.type === 'select') {
	                              const promise = (dataCache.columnOptions[c.id]
                                ? Promise.resolve(dataCache.columnOptions[c.id])
                                : fetch(`?column_options=${c.id}`)
                                    .then(r=>r.json())
                                    .then(options => {
                                      dataCache.columnOptions[c.id] = options;
                                      return options;
                                    }))
	                                .then(options=>{
	                                  let selectHtml = `<select class="cell-select" data-task="${taskId}" data-column="${Number(c.id)}"
	                                                           style="border:none;background:transparent;width:100%;padding:2px;"
	                                                           onchange="saveCellValue(this)">
	                                                     <option value="">-- Choisir --</option>`;
	                                  options.forEach(opt=>{
	                                    const selected = cellValue == opt.id ? 'selected' : '';
	                                    const optionColor = opt.color || '#87CEEB';
	                                    selectHtml += `<option value="${Number(opt.id)}" ${selected} style="background:${escapeHtml(optionColor)};">${escapeHtml(opt.label)}</option>`;
	                                  });
                                  selectHtml += '</select>';
                                  return selectHtml;
                                });
                              cellPromises.push(promise);
                            } else if(c.type === 'number') {
		                              const inputHtml = `<input type="text" class="cell-input cell-number"
	                                data-task="${taskId}"
	                                data-column="${Number(c.id)}"
	                                value="${escapeHtml(cellValue)}"
	                                style="border:none;background:transparent;width:100%;padding:2px;text-align:right;"
                                onblur="saveCellValue(this)"
                                onkeydown="if(event.key==='Enter') saveCellValue(this)"
                                oninput="validateNumberInput(this)">`;
                              cellPromises.push(Promise.resolve(inputHtml));
                            } else if(c.type === 'tags') {
                              const promise = (dataCache.columnOptions[c.id]
                                ? Promise.resolve(dataCache.columnOptions[c.id])
                                : fetch(`?column_options=${c.id}`)
                                    .then(r=>r.json())
                                    .then(options => {
                                      dataCache.columnOptions[c.id] = options;
                                      return options;
                                    }))
	                                .then(options=>{
	                                  let selectedTags = [];
	                                  try {
	                                    selectedTags = cellValue ? JSON.parse(cellValue) : [];
	                                  } catch (e) {
	                                    selectedTags = [];
	                                  }

	                                  let tagsHtml = `
	                                    <div class="tags-cell" data-task="${taskId}" data-column="${Number(c.id)}" style="min-height:30px;padding:3px;border:1px dashed #ddd;cursor:pointer;" onclick="openTagsSelector(this)">
	                                      <div class="selected-tags" style="display:flex;flex-wrap:wrap;gap:3px;margin-bottom:5px;">
	                                  `;

	                                  selectedTags.forEach(tagId => {
	                                    const tag = options.find(opt => opt.id == tagId);
	                                    if(tag) {
	                                      tagsHtml += `
	                                        <span class="tag-item" data-tag-id="${Number(tag.id)}" style="background:${escapeHtml(tag.color || '#87CEEB')};color:white;padding:2px 6px;border-radius:12px;font-size:11px;display:flex;align-items:center;gap:4px;">
	                                          ${escapeHtml(tag.label)}
	                                          <span class="remove-tag" onclick="removeTag(event, this)" style="cursor:pointer;font-weight:bold;">×</span>
	                                        </span>
                                      `;
                                    }
                                  });
                                  
                                  tagsHtml += `
                                      </div>
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
	                                <div class="deadline-cell" data-task="${taskId}" data-column="${Number(c.id)}">
	                                  <div style="display:flex;gap:5px;margin-bottom:3px;">
	                                    <input type="date" class="deadline-start" value="${escapeHtml(startDate)}"
	                                           style="border:1px solid #ddd;padding:2px;font-size:10px;width:48%;"
	                                           placeholder="Début"
	                                           onchange="updateDeadline(this)">
	                                    <input type="date" class="deadline-end" value="${escapeHtml(endDate)}"
	                                           style="border:1px solid #ddd;padding:2px;font-size:10px;width:48%;"
                                           placeholder="Fin"
                                           onchange="updateDeadline(this)">
                                  </div>
	                                  <div class="days-remaining ${daysClass}" style="font-size:11px;text-align:center;font-weight:bold;">${escapeHtml(daysText)}</div>
	                                </div>
                              `;
                              cellPromises.push(Promise.resolve(inputHtml));
                            } else if(c.type === 'user') {
                              const promise = (dataCache.users 
                                ? Promise.resolve(dataCache.users)
                                : fetch('?users_list').then(r=>r.json()).then(users => {
                                    dataCache.users = users;
                                    return users;
                                  }))
                                .then(users=>{
	                                  let selectHtml = `<select class="cell-select user-select" data-task="${taskId}" data-column="${Number(c.id)}"
	                                                           style="border:none;background:transparent;width:100%;padding:2px;"
	                                                           onchange="saveCellValue(this)">
	                                                     <option value="">-- Non assigné --</option>`;
	                                  users.forEach(user=>{
	                                    const selected = cellValue == user.id ? 'selected' : '';
	                                    selectHtml += `<option value="${Number(user.id)}" ${selected}>${escapeHtml(user.name)}</option>`;
	                                  });
                                  selectHtml += '</select>';
                                  
                                  if (cellValue) {
                                    const selectedUser = users.find(u => u.id == cellValue);
                                    if (selectedUser) {
	                                      const initials = selectedUser.name.split(' ').map(n => n[0]).join('').substr(0, 2).toUpperCase();
	                                      return `
	                                        <div class="user-cell" data-task="${taskId}" data-column="${Number(c.id)}" style="cursor:pointer;" onclick="openUserSelector(this)">
	                                          <div class="user-avatar" title="${escapeHtml(selectedUser.name)}">${escapeHtml(initials)}</div>
	                                          <span>${escapeHtml(selectedUser.name)}</span>
	                                          ${selectHtml.replace('style="', 'style="display:none;')}
	                                        </div>
                                      `;
                                    }
                                  }
                                  
	                                  return `<div class="user-cell unassigned" data-task="${taskId}" data-column="${Number(c.id)}" style="cursor:pointer;" onclick="openUserSelector(this)">${selectHtml}</div>`;
	                                });
                              cellPromises.push(promise);
                            } else if(c.type === 'date') {
		                              const inputHtml = `<input type="date" class="cell-input cell-date"
	                                                        data-task="${taskId}"
	                                                        data-column="${Number(c.id)}"
	                                                        value="${escapeHtml(cellValue)}"
                                                        style="border:none;background:transparent;width:100%;padding:2px;cursor:pointer;"
                                                        onblur="saveCellValue(this)"
                                                        onchange="saveCellValue(this)">`;
                              cellPromises.push(Promise.resolve(inputHtml));
                            } else {
		                              const inputHtml = `<input type="text" class="cell-input"
	                                                        data-task="${taskId}"
	                                                        data-column="${Number(c.id)}"
	                                                        value="${escapeHtml(cellValue)}"
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
	                          const $taskRow = $(`<tr class="task-row" data-id="${taskId}" data-parent-id="${parentTaskId || ''}" style="cursor:pointer;">${tds}</tr>`);

                          $taskRow.find('td:nth-child(1)').click(function(e) {
                            if ($(e.target).closest('button').length) return;

                            const taskName = $(this).find('.task-label').text();
                            const groupName = $grp.find('.group-label').text();
                            const taskColumnLabel = $grp.find('.task-column-label').text();
                            openTaskDetail(t.id, taskName, groupName, taskColumnLabel);
                          });

                          resolve($taskRow);
                        });
                      });
                    });
                    
                    Promise.all(taskPromises).then((taskRows) => {
                      taskRows.forEach($row => {
                        $grp.find('tbody.tasks-tbody').append($row);
                      });
                       
                      $grp.find('select.cell-select').each(function(){
                        applySelectColor($(this));
                      });
                      
                      initTaskSortable();
                      initColumnSortable();
                      updateCollapsedRows(); // Appliquer l'état collapse après le rendu
                    });
                  });
              });
          });

          initGroupSortable();

          attachEventHandlers(wid);
          
          setTimeout(function() {
            if ($('#workspace-search').length && $('#workspace-search').val().trim()) {
              const searchTerm = $('#workspace-search').val();
              $('#workspace-search').trigger('input');
            }
            initWorkspaceSearch();
          }, 100);
        });
  }

  function attachEventHandlers(wid) {
    $('#group-list').off('click', '.candidates-toggle').on('click', '.candidates-toggle', function(e) {
      e.stopPropagation();
      const $button = $(this);
      setClientNeedCandidatesExpanded($button, $button.attr('aria-expanded') !== 'true');
    });

    $('#group-list').off('click', '.candidate-detail-link').on('click', '.candidate-detail-link', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const candidateId = Number($(this).data('candidate-id'));
      if (!candidateId) return;
      const candidateName = $(this).data('candidate-name') || $(this).text().trim();
      openTaskDetail(candidateId, candidateName, 'Candidat', 'Candidat');
    });

    $('#group-list').off('click','.add-column-btn').on('click','.add-column-btn',function(e){
      e.stopPropagation();
      const gid = $(this).data('gid');
      
      CustomPopup.prompt('Nom de la colonne :', function(lbl) {
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
          loadKpiExportGroups();
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
      }, '', 'Ajouter une colonne');
    });

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

    $('#group-list')
      .off('click','.rename-group').on('click','.rename-group',function(){
        const $g=$(this).closest('.group');
        const gid=$g.data('id');
        const old=$g.find('.group-label').text();
        CustomPopup.prompt('Nouveau nom du groupe :', function(nw) {
          if(!nw) return;
          const fd=new FormData();
          fd.append('rename_group_id',gid);
          fd.append('group_label',nw);
          fd.append('token',token);
          fetch('',{method:'POST',body:fd}).then(()=>{
            loadGroups(wid);
            loadKpiExportGroups();
          });
        }, old, 'Renommer le groupe');
      })
      .off('click','.duplicate-group').on('click','.duplicate-group',function(){
        const $g=$(this).closest('.group');
        const gid=$g.data('id');
        const old=$g.find('.group-label').text();
        CustomPopup.prompt('Nom du nouveau groupe :', function(nw) {
          if(!nw) return;
          const fd=new FormData();
          fd.append('duplicate_group_id',gid);
          fd.append('new_group_label',nw);
          fd.append('token',token);
          fetch('',{method:'POST',body:fd}).then(()=>{
            loadGroups(wid);
            loadKpiExportGroups();
          });
        }, old+' (copie)', 'Dupliquer le groupe');
      })
      .off('click','.delete-group').on('click','.delete-group',function(){
        const $g=$(this).closest('.group');
        const gid=$g.data('id');
        CustomPopup.confirm('Supprimer ce groupe ?', function(result) {
          if(!result) return;
          const fd=new FormData();
          fd.append('delete_group_id',gid);
          fd.append('token',token);
          fetch('',{method:'POST',body:fd}).then(()=>{
            loadGroups(wid);
            loadKpiExportGroups();
          });
        });
      })
      .off('click','.add-row-btn').on('click','.add-row-btn',function(){
        const gid=$(this).closest('.group').data('id');
        const taskColumnLabel = $(this).closest('.group').find('.task-column-label').text().toLowerCase();
        CustomPopup.prompt(`Nom de ${taskColumnLabel} :`, function(lbl) {
          if(!lbl) return;
          const fd=new FormData();
          fd.append('add_task_group_id',gid);
          fd.append('task_label',lbl);
          fd.append('token',token);
          fetch('',{method:'POST',body:fd}).then(()=>{
            loadGroups(wid);
            loadKpiExportGroups();
          });
        }, '', `Ajouter une ${taskColumnLabel}`);
      })
      .off('click','.add-subtask-btn').on('click','.add-subtask-btn',function(e){
        e.stopPropagation();
        const parentTaskId = $(this).data('task-id');
        const gid = $(this).closest('.group').data('id');
        const $row = $(this).closest('tr');
        
        CustomPopup.prompt('Nom de la sous-tâche :', function(lbl) {
          if(!lbl) return;
          const fd = new FormData();
          fd.append('add_task_group_id', gid);
          fd.append('task_label', lbl);
          fd.append('parent_task_id', parentTaskId);
          fd.append('token', token);
        fetch('', {method:'POST', body:fd}).then(() => {
            loadGroups(wid);
            loadKpiExportGroups();
          });
        }, '', 'Ajouter une sous-tâche');
      })
      .off('click','.task-column-label').on('click','.task-column-label',function(e){
        e.stopPropagation();
        const gid = $(this).data('gid');
        const $group = $(this).closest('.group');
        const currentLabel = $(this).text();
        
        CustomPopup.prompt('Nom de la colonne :', function(newLabel) {
          if(!newLabel || newLabel === currentLabel) return;
          
          const fd = new FormData();
          fd.append('update_task_column_label', gid);
          fd.append('task_column_label', newLabel);
          fd.append('token', token);
          
          fetch('', {method: 'POST', body: fd}).then(() => {
            $group.find('.task-column-label').text(newLabel);
            $group.find('.add-row-btn').text(`+ Ajouter ${newLabel.toLowerCase()}`);
          });
        }, currentLabel, 'Modifier le nom de la colonne');
      })
      .off('click','.rename-column-btn').on('click','.rename-column-btn',function(e){
        e.stopPropagation();
        const cid = $(this).data('cid');
        const old = $(this).closest('.column-menu').siblings('.column-label').text();
        CustomPopup.prompt('Nouveau nom de la colonne :', function(nw) {
          if(!nw) return;
          const fd = new FormData();
          fd.append('rename_column_id', cid);
          fd.append('rename_column_label', nw);
          fd.append('token', token);
          fetch('',{method:'POST',body:fd}).then(()=>{
            loadGroups(wid);
            loadKpiExportGroups();
          });
        }, old, 'Renommer la colonne');
      })
      .off('click','.delete-column-btn').on('click','.delete-column-btn',function(e){
        e.stopPropagation();
        const cid = $(this).data('cid');
        CustomPopup.confirm('Supprimer cette colonne ?', function(result) {
          if(!result) return;
          const fd = new FormData();
          fd.append('delete_column_id', cid);
          fd.append('token', token);
          fetch('',{method:'POST',body:fd}).then(()=>{
            loadGroups(wid);
            loadKpiExportGroups();
          });
        });
      })
      .off('click','.manage-options-btn').on('click','.manage-options-btn',function(e){
        e.stopPropagation();
        const cid = $(this).data('cid');
        
        manageColumnOptions(cid, token, () => loadGroups(wid));
      })
      .off('click','.sort-asc-btn').on('click','.sort-asc-btn',function(e){
        e.stopPropagation();
        const cid = $(this).data('cid');
        const type = $(this).data('type');
        const $group = $(this).closest('.group');
        sortColumn($group, cid, type, 'asc');
        
        $group.find('.column-sort-indicator').text('').removeClass('asc desc');
        $group.find(`[data-cid="${cid}"].column-sort-indicator`).text('↑').addClass('asc');
        
        $('.column-menu').removeClass('show');
        setTimeout(() => $('.column-menu').hide(), 200);
      })
      .off('click','.sort-desc-btn').on('click','.sort-desc-btn',function(e){
        e.stopPropagation();
        const cid = $(this).data('cid');
        const type = $(this).data('type');
        const $group = $(this).closest('.group');
        sortColumn($group, cid, type, 'desc');
        
        $group.find('.column-sort-indicator').text('').removeClass('asc desc');
        $group.find(`[data-cid="${cid}"].column-sort-indicator`).text('↓').addClass('desc');
        
        $('.column-menu').removeClass('show');
        setTimeout(() => $('.column-menu').hide(), 200);
      })
      .off('click','.column-menu-btn').on('click','.column-menu-btn',function(e){
        e.stopPropagation();
        $('.column-menu').removeClass('show');
        setTimeout(() => {
          const menu = $(this).siblings('.column-menu');
          menu.show();
          setTimeout(() => menu.addClass('show'), 10);
        }, 200);
      })
      .off('click','.column-menu').on('click','.column-menu',function(e){
        e.stopPropagation();
      });
      
    $(document).on('click', function(e) {
      if (!$(e.target).closest('.column-menu, .column-menu-btn').length) {
        $('.column-menu').removeClass('show');
        setTimeout(() => $('.column-menu').hide(), 200);
      }
    });
  }

  function sortColumn($group, columnId, columnType, direction) {
    const $tbody = $group.find('tbody.tasks-tbody');
    const $rows = $tbody.find('tr.task-row').toArray();
    
    const $headers = $group.find('th');
    let columnIndex = -1;
    
    $headers.each(function(index) {
      const $header = $(this);
      const $label = $header.find('.column-label');
      if ($label.data('cid') == columnId) {
        columnIndex = index;
        return false;
      }
    });
    
    if (columnIndex === -1) return;
    
    $rows.sort(function(a, b) {
      const $cellA = $(a).find('td').eq(columnIndex);
      const $cellB = $(b).find('td').eq(columnIndex);
      
      let valueA, valueB;
      
      switch(columnType) {
        case 'text':
          valueA = $cellA.find('input').val() || $cellA.text() || '';
          valueB = $cellB.find('input').val() || $cellB.text() || '';
          valueA = valueA.toLowerCase();
          valueB = valueB.toLowerCase();
          break;
          
        case 'number':
          valueA = parseFloat($cellA.find('input').val() || '0') || 0;
          valueB = parseFloat($cellB.find('input').val() || '0') || 0;
          break;
          
        case 'date':
        case 'deadline':
          if (columnType === 'deadline') {
            valueA = $cellA.find('.deadline-end').val() || '';
            valueB = $cellB.find('.deadline-end').val() || '';
          } else {
            valueA = $cellA.find('input[type="date"]').val() || '';
            valueB = $cellB.find('input[type="date"]').val() || '';
          }
          valueA = valueA ? new Date(valueA) : new Date('1970-01-01');
          valueB = valueB ? new Date(valueB) : new Date('1970-01-01');
          break;
          
        case 'select':
          valueA = $cellA.find('select option:selected').text() || '';
          valueB = $cellB.find('select option:selected').text() || '';
          valueA = valueA.toLowerCase();
          valueB = valueB.toLowerCase();
          break;
          
        case 'user':
          valueA = $cellA.find('span').text() || $cellA.find('select option:selected').text() || '';
          valueB = $cellB.find('span').text() || $cellB.find('select option:selected').text() || '';
          valueA = valueA.toLowerCase();
          valueB = valueB.toLowerCase();
          break;
          
        case 'tags':
          valueA = $cellA.find('.tag-item').length;
          valueB = $cellB.find('.tag-item').length;
          break;
          
        default:
          valueA = $cellA.text() || '';
          valueB = $cellB.text() || '';
          valueA = valueA.toLowerCase();
          valueB = valueB.toLowerCase();
      }
      
      let result = 0;
      if (columnType === 'number' || columnType === 'date' || columnType === 'deadline' || columnType === 'tags') {
        result = valueA - valueB;
      } else {
        result = valueA.localeCompare(valueB);
      }
      
      return direction === 'desc' ? -result : result;
    });
    
    $tbody.empty().append($rows);
    
    $rows.forEach(row => {
      const $row = $(row);
      const taskId = $row.data('id');
      const $group = $row.closest('.group');
      
      $row.find('td:nth-child(1)').off('click').on('click', function(e) {
        if ($(e.target).is('button')) return;
        
        const taskName = $(this).text();
        const groupName = $group.find('.group-label').text();
        const taskColumnLabel = $group.find('.task-column-label').text();
        openTaskDetail(taskId, taskName, groupName, taskColumnLabel);
      });
      
      $row.find('select.cell-select').each(function(){
        applySelectColor($(this));
      });
    });
  }

  function manageColumnOptions(cid, token, onComplete) {
    fetch(`?column_options=${cid}`)
      .then(r=>r.json())
      .then(options=>{
        const optionsModal = $(`
          <div id="options-modal" class="custom-popup-overlay show">
            <div class="custom-popup" style="max-width: 600px; width: 90%;">
              <div class="custom-popup-header">
                <h3 class="custom-popup-title">Gérer les options</h3>
              </div>
              <div class="custom-popup-content">
                <div id="options-list" style="margin:15px 0;max-height:350px;overflow-y:auto;padding:10px;background:#f8f9fa;border-radius:6px;">
                  ${options.map(opt => `
                    <div class="option-item" data-option-id="${opt.id}" style="display:flex;align-items:center;gap:12px;margin-bottom:12px;padding:12px;border:1px solid #e9ecef;border-radius:8px;background:#fff;transition:all 0.2s ease;">
                      <div class="color-preview" style="width:24px;height:24px;border-radius:6px;background:${opt.color || '#87CEEB'};border:2px solid #e9ecef;cursor:pointer;transition:all 0.2s ease;" title="Cliquer pour changer la couleur"></div>
                      <span class="option-label-display" style="flex:1;padding:8px;font-weight:500;color:#333;">${opt.label}</span>
                      <button class="edit-option-btn modal-btn modal-btn-edit" data-option-id="${opt.id}" style="padding:6px 10px;background:#0073ea;color:white;border:none;cursor:pointer;border-radius:4px;font-size:12px;transition:all 0.2s ease;">✎</button>
                      <button class="delete-option-btn modal-btn modal-btn-danger" data-option-id="${opt.id}" style="padding:6px 10px;background:#e2445c;color:white;border:none;cursor:pointer;border-radius:4px;font-size:12px;transition:all 0.2s ease;">✖</button>
                    </div>
                  `).join('')}
                </div>
                
                <div class="add-option-section" style="border-top:1px solid #e9ecef;padding-top:20px;margin-top:20px;">
                  <h4 style="margin:0 0 15px 0;color:#333;font-size:16px;">Ajouter une nouvelle option</h4>
                  <div class="add-option-form" style="display:flex;gap:10px;align-items:center;">
                    <input type="text" id="new-option-label" placeholder="Nom de l'option" style="flex:1;padding:10px;border:1px solid #e9ecef;border-radius:6px;font-size:14px;transition:all 0.2s ease;">
                    <div id="new-option-color-preview" class="new-option-color-preview" style="width:36px;height:36px;border-radius:6px;background:#0073ea;border:2px solid #e9ecef;cursor:pointer;transition:all 0.2s ease;" title="Cliquer pour choisir une couleur"></div>
                    <button id="add-option-btn" class="custom-popup-btn custom-popup-btn-primary">Ajouter</button>
                  </div>
                </div>
                
                <div class="custom-popup-buttons" style="margin-top:25px;">
                  <button id="close-options" class="custom-popup-btn custom-popup-btn-secondary">Fermer</button>
                </div>
              </div>
            </div>
          </div>
        `);
        
        $('body').append(optionsModal);
        
        let selectedColor = '#0073ea';
        
        function createColorPicker(currentColor, callback) {
          const colorModal = $(`
            <div id="color-picker-modal" class="custom-popup-overlay show">
              <div class="custom-popup" style="max-width: 450px;">
                <div class="custom-popup-header">
                  <h3 class="custom-popup-title">Choisir une couleur</h3>
                </div>
                <div class="custom-popup-content">
                  <div class="section-title">Couleurs prédéfinies</div>
                  <div class="color-grid" style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:20px;">
                    ${['#e74c3c', '#27ae60', '#3498db', '#f39c12', '#9b59b6', '#e67e22', '#1abc9c', '#8e44ad', '#2ecc71', '#f1c40f'].map(color => `
                      <div class="preset-color ${currentColor === color ? 'selected' : ''}" data-color="${color}" style="width:44px;height:44px;background:${color};border:3px solid ${currentColor === color ? '#0073ea' : '#e9ecef'};cursor:pointer;border-radius:8px;transition:all 0.2s ease;"></div>
                    `).join('')}
                  </div>
                  
                  <div class="custom-color-section" style="display:flex;align-items:center;gap:15px;margin-bottom:20px;">
                    <div>
                      <div class="section-title">Couleur personnalisée</div>
                      <input type="color" id="custom-color-picker" value="${currentColor}" class="custom-color-picker" style="width:60px;height:40px;border:none;cursor:pointer;border-radius:6px;border:2px solid #e9ecef;">
                    </div>
                    <div class="color-display" style="padding:8px 15px;background:${currentColor};color:white;border-radius:6px;font-weight:600;font-size:14px;text-shadow:0 1px 2px rgba(0,0,0,0.3);border:1px solid rgba(0,0,0,0.1);">${currentColor}</div>
                  </div>
                  
                  <div class="custom-popup-buttons">
                    <button id="apply-color" class="custom-popup-btn custom-popup-btn-primary">Appliquer</button>
                    <button id="cancel-color" class="custom-popup-btn custom-popup-btn-secondary">Annuler</button>
                  </div>
                </div>
              </div>
            </div>
          `);
          
          $('body').append(colorModal);
          
          let tempColor = currentColor;
          
          $('.preset-color').hover(
            function() { $(this).css('transform', 'scale(1.1)'); },
            function() { $(this).css('transform', 'scale(1)'); }
          ).click(function(){
            $('.preset-color').removeClass('selected').css('border-color', '#e9ecef');
            $(this).addClass('selected').css('border-color', '#0073ea');
            tempColor = $(this).data('color');
            $('#custom-color-picker').val(tempColor);
            $('#color-display').css('background', tempColor).text(tempColor);
          });
          
          $('#custom-color-picker').on('input', function(){
            tempColor = $(this).val();
            $('#color-display').css('background', tempColor).text(tempColor);
            $('.preset-color').css('border-color', '#ccc');
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
        
        function rgbToHex(rgb) {
          if (rgb.indexOf('#') === 0) return rgb;
          const result = rgb.match(/\d+/g);
          if (!result) return '#87CEEB';
          return '#' + ((1 << 24) + (parseInt(result[0]) << 16) + (parseInt(result[1]) << 8) + parseInt(result[2])).toString(16).slice(1);
        }
        
        $('#new-option-color-preview').click(function(){
          createColorPicker(selectedColor, function(newColor){
            selectedColor = newColor;
            $('#new-option-color-preview').css('background', newColor);
          });
        });
        
        $(document).on('click', '.color-preview', function(){
          const optionId = $(this).closest('.option-item').data('option-id');
          const currentColor = rgbToHex($(this).css('background-color'));
          const $preview = $(this);
          
          createColorPicker(currentColor, function(newColor){
            $preview.css('background', newColor);
            
            const fd = new FormData();
            fd.append('update_option_color', optionId);
            fd.append('option_color', newColor);
            fd.append('token', token);
            
            fetch('', {method: 'POST', body: fd})
              .then(() => console.log('Couleur mise à jour'));
          });
        });
        
        $('#options-list').off('click', '.edit-option-btn').on('click', '.edit-option-btn', function(e){
          e.preventDefault();
          e.stopPropagation();
          
          const optionId = $(this).data('option-id');
          const $item = $(this).closest('.option-item');
          const $labelDisplay = $item.find('.option-label-display');
          const currentLabel = $labelDisplay.text();
          
          CustomPopup.prompt('Nouveau nom de l\'option :', function(newLabel) {
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
          }, currentLabel);
        });
        
        $('#options-list').off('click', '.delete-option-btn').on('click', '.delete-option-btn', function(e){
          e.preventDefault();
          e.stopPropagation();
          
          const optionId = $(this).data('option-id');
          const $button = $(this);
          
          CustomPopup.confirm('Supprimer cette option ?', function(result) {
            if(!result) return;
            
            const fd = new FormData();
            fd.append('delete_option_id', optionId);
            fd.append('token', token);
            
            fetch('', {method: 'POST', body: fd})
              .then(() => {
                $button.closest('.option-item').remove();
              })
              .catch(e => console.error('Erreur lors de la suppression:', e));
          });
        });
        
        $('#add-option-btn').click(function(){
          const label = $('#new-option-label').val().trim();
          
          if(!label) {
            CustomPopup.error('Veuillez saisir un label', 'Champ obligatoire');
            return;
          }
          
          const fd = new FormData();
          fd.append('add_option_column_id', cid);
          fd.append('option_label', label);
          fd.append('option_color', selectedColor);
          fd.append('token', token);
          
          fetch('', {method: 'POST', body: fd})
            .then(() => {
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
              
              
              $('#new-option-label').val('');
              selectedColor = '#87CEEB';
              $('#new-option-color-preview').css('background', selectedColor);
            })
            .catch(e => console.error('Erreur lors de l\'ajout:', e));
        });
        
        $('#close-options').click(function(){
          optionsModal.remove();
          if(onComplete) onComplete();
        });
        
        optionsModal.click(function(e){
          if(e.target === optionsModal[0]) {
            optionsModal.remove();
            if(onComplete) onComplete();
          }
        });
      });
  }

  // Fonctions pour gérer le collapse/expand des sous-tâches
  window.toggleCollapse = function(taskId) {
    if (taskCollapseState.has(taskId)) {
      taskCollapseState.delete(taskId);
    } else {
      taskCollapseState.add(taskId);
    }
    updateCollapsedRows();
  };

  function updateCollapsedRows() {
    const flattenNeedRows = isClientNeedWorkspaceLabel(currentWorkspaceLabel);

    document.querySelectorAll('.task-row').forEach(row => {
      const taskId = row.dataset.id;
      const parentId = row.dataset.parentId;

      if (flattenNeedRows) {
        row.classList.remove('collapsed-children');
        row.classList.remove('task-row-hidden');
        row.style.display = '';
        return;
      }
      
      // Si le parent de cette tâche est collapsed, on la masque
      if (parentId && taskCollapseState.has(parseInt(parentId))) {
        row.classList.add('collapsed-children');
      } else {
        row.classList.remove('collapsed-children');
      }
      
      // Mettre à jour le bouton collapse
      const collapseBtn = row.querySelector('.collapse-toggle');
      if (collapseBtn) {
        const isCollapsed = taskCollapseState.has(parseInt(taskId));
        collapseBtn.textContent = isCollapsed ? '▶' : '▼';
      }
    });
  }

  // Trier les tâches de manière hiérarchique (parent suivi de ses enfants)
  function sortTasksHierarchically(tasks) {
    const result = [];
    const taskMap = new Map(tasks.map(t => [Number(t.id), t]));
    const processed = new Set();
    const originalOrder = new Map(tasks.map((t, index) => [Number(t.id), index]));

    const byPosition = (a, b) => {
      const positionA = Number.isFinite(Number(a.position)) ? Number(a.position) : originalOrder.get(Number(a.id));
      const positionB = Number.isFinite(Number(b.position)) ? Number(b.position) : originalOrder.get(Number(b.id));
      if (positionA !== positionB) return positionA - positionB;
      return Number(a.id || 0) - Number(b.id || 0);
    };
    
    function addTaskAndChildren(taskId) {
      taskId = Number(taskId);
      if (processed.has(taskId)) return;
      
      const task = taskMap.get(taskId);
      if (!task) return;
      
      processed.add(taskId);
      result.push(task);
      
      // Ajouter tous les enfants de cette tâche
      tasks
        .filter(t => Number(t.parent_task_id || 0) === taskId)
        .sort(byPosition)
        .forEach(t => addTaskAndChildren(t.id));
    }
    
    // Commencer par les tâches sans parent (triées par position)
    tasks
      .filter(t => !t.parent_task_id)
      .sort(byPosition)
      .forEach(t => addTaskAndChildren(t.id));

    tasks
      .filter(t => !processed.has(Number(t.id)))
      .sort(byPosition)
      .forEach(t => addTaskAndChildren(t.id));
    
    return result;
  }

  let searchTimeout;

  function initWorkspaceSearch() {
    const $searchInput = $('#workspace-search');
    const $clearButton = $('#clear-workspace-search');
    const $searchInfo = $('#search-info');
    
    if ($searchInput.length === 0) return;
    
    function normalizeText(text) {
      return text.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    }
    
    function performWorkspaceSearch(searchTerm) {
      const $groups = $('.group');
      let totalItems = 0;
      let visibleItems = 0;
      let foundInGroups = new Set();
      
      $('.search-highlight').contents().unwrap();
      $('td').css('background-color', '');
      
      if (!searchTerm.trim()) {
        $groups.show();
        $groups.find('tr').removeClass('task-row-hidden').show();
        $groups.find('td').css('background-color', '');
        $searchInfo.hide();
        $('#no-results-message').remove();
        return;
      }
      
      const normalizedSearchTerm = normalizeText(searchTerm.trim());
      const searchRegex = new RegExp(searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
      
      $groups.each(function() {
        const $group = $(this);
        const groupId = $group.attr('data-id');
        let hasMatchInGroup = false;
        
        const $groupLabel = $group.find('.group-label');
        const groupLabelText = $groupLabel.text().trim();
        if (normalizeText(groupLabelText).includes(normalizedSearchTerm)) {
          const highlightedText = highlightEscapedText(groupLabelText, searchRegex);
          $groupLabel.html(highlightedText);
          hasMatchInGroup = true;
          totalItems++;
          visibleItems++;
        }
        
        const $rows = $group.find('tbody.tasks-tbody > tr');
        $rows.each(function() {
          const $row = $(this);
          let hasMatchInRow = false;
          
          $row.children('td').each(function() {
            const $cell = $(this);
            let cellText = '';
            
            if ($cell.find('input[type="text"], input[type="date"], textarea').length > 0) {
              cellText = $cell.find('input, textarea').val() || '';
            } else if ($cell.find('select').length > 0) {
              const selectedText = $cell.find('select option:selected').text() || '';
              cellText = selectedText.replace('-- Choisir --', '').trim();
            } else if ($cell.find('.selected-tags').length > 0) {
              $cell.find('.tag-item').each(function() {
                cellText += $(this).text().replace('×', '').trim() + ' ';
              });
            } else if ($cell.find('.days-remaining').length > 0) {
              cellText = $cell.find('.days-remaining').text();
            } else if ($cell.find('.user-cell').length > 0) {
              cellText = $cell.find('.user-cell').text().trim();
            } else {
              cellText = $cell.text().trim();
            }
            
            if (cellText && normalizeText(cellText).includes(normalizedSearchTerm)) {
              if ($cell.find('.candidates-panel').length > 0) {
                $cell.css('background-color', '#fff3cd');
                const $candidatePanel = $cell.find('.candidates-panel').first();
                $candidatePanel.find('tbody tr').each(function() {
                  const $candidateRow = $(this);
                  $candidateRow.children('td').each(function() {
                    const $candidateCell = $(this);
                    const candidateCellText = $candidateCell.text().trim();

                    if (!candidateCellText || !normalizeText(candidateCellText).includes(normalizedSearchTerm)) {
                      return;
                    }

                    if ($candidateCell.find('.candidate-detail-link').length > 0) {
                      const $candidateLink = $candidateCell.find('.candidate-detail-link').first();
                      const candidateLinkText = $candidateLink.text().trim();
                      $candidateLink.html(highlightEscapedText(candidateLinkText, searchRegex));
                    } else {
                      $candidateCell.css('background-color', '#fff3cd');
                    }
                  });
                });
              } else if ($cell.find('input, textarea').length === 0 && 
                  $cell.find('select').length === 0 && 
                  $cell.find('.tag-item').length === 0 && 
                  $cell.find('.user-cell').length === 0) {
                const highlightedText = highlightEscapedText(cellText, searchRegex);
                $cell.html(highlightedText);
              } else if ($cell.find('select').length > 0) {
                $cell.css('background-color', '#fff3cd');
              } else if ($cell.find('.tag-item').length > 0) {
                $cell.find('.tag-item').each(function() {
                  const $tag = $(this);
                  const tagText = $tag.text().replace('×', '').trim();
                  if (normalizeText(tagText).includes(normalizedSearchTerm)) {
                    const highlightedTagText = highlightEscapedText(tagText, searchRegex);
                    $tag.html(highlightedTagText + '<span class="remove-tag" onclick="removeTag(event, this)" style="cursor:pointer;font-weight:bold;">×</span>');
                  }
                });
              } else if ($cell.find('.user-cell').length > 0) {
                $cell.css('background-color', '#fff3cd');
              } else if ($cell.find('input, textarea').length > 0) {
                $cell.css('background-color', '#fff3cd');
              }
              hasMatchInRow = true;
              hasMatchInGroup = true;
            }
          });
          
          if (hasMatchInRow) {
            $row.removeClass('task-row-hidden').show();
            totalItems++;
            visibleItems++;
          } else {
            $row.addClass('task-row-hidden').hide();
          }
        });
        
        $group.find('.column-label').each(function() {
          const $colLabel = $(this);
          const colText = $colLabel.text().trim();
          if (normalizeText(colText).includes(normalizedSearchTerm)) {
            const highlightedText = highlightEscapedText(colText, searchRegex);
            $colLabel.html(highlightedText);
            hasMatchInGroup = true;
            totalItems++;
            visibleItems++;
          }
        });
        
        if (hasMatchInGroup) {
          foundInGroups.add(groupId);
          $group.show();
          if ($group.find('.group-body').is(':hidden')) {
            $group.find('.group-body').show();
            $group.find('.group-toggle').text('▼');
          }
        } else {
          $group.hide();
        }
      });
      
      if (visibleItems === 0) {
        $searchInfo.html(`Aucun résultat trouvé pour "<strong>${escapeHtml(searchTerm)}</strong>"`).show();
        
        if ($('.group:visible').length === 0 && $('.group').length > 0) {
          if ($('#no-results-message').length === 0) {
            $('#group-list').append(`
              <div id="no-results-message" style="text-align:center;padding:40px;background:#f8f9fa;border-radius:8px;margin-top:20px;border:2px dashed #ddd;">
                <div style="font-size:48px;color:#ccc;margin-bottom:15px;">🔍</div>
                <h3 style="color:#666;margin-bottom:10px;">Aucun résultat trouvé</h3>
                <p style="color:#999;margin:0;">Essayez de modifier votre terme de recherche dans cet espace de travail.</p>
              </div>
            `);
          }
        }
      } else {
        $searchInfo.html(`${visibleItems} élément(s) trouvé(s) pour "<strong>${escapeHtml(searchTerm)}</strong>"`).show();
        $('#no-results-message').remove();
      }
    }
    
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
    
    $searchInput.on('input', function() {
      clearTimeout(searchTimeout);
      const searchTerm = $(this).val();
      
      if (searchTerm.trim()) {
        $clearButton.show();
      } else {
        $clearButton.hide();
      }
      
      searchTimeout = setTimeout(function() {
        performWorkspaceSearch(searchTerm);
      }, 300);
    });
    
    $searchInput.on('keypress', function(e) {
      if (e.which === 13) {
        clearTimeout(searchTimeout);
        performWorkspaceSearch($(this).val());
      }
    });
    
    $clearButton.on('click', function() {
      $searchInput.val('');
      $clearButton.hide();
      performWorkspaceSearch('');
    });
    
    $(document).on('keydown', function(e) {
      if (e.ctrlKey && e.key === 'f' && $searchInput.is(':visible')) {
        e.preventDefault();
        $searchInput.focus();
      }
      
      if (e.key === 'Escape' && $searchInput.is(':focus')) {
        $searchInput.val('');
        $clearButton.hide();
        performWorkspaceSearch('');
        $searchInput.blur();
      }
    });
  }
  
  $(document).ready(function() {
  });

  window.CustomPopup = {
    show: function(options) {
      const defaults = {
        type: 'info',
        title: 'Information',
        message: '',
        showInput: false,
        inputPlaceholder: '',
        inputValue: '',
        buttons: [
          {
            text: 'OK',
            class: 'custom-popup-btn-primary',
            callback: null
          }
        ]
      };
      
      const config = Object.assign({}, defaults, options);
      
      $('.custom-popup-overlay').remove();
      
      const headerClass = config.type === 'info' ? '' : config.type;
      
      let inputHtml = '';
      if (config.showInput) {
        inputHtml = `<input type="text" class="custom-popup-input" placeholder="${config.inputPlaceholder}" value="${config.inputValue}">`;
      }
      
      let buttonsHtml = '';
      config.buttons.forEach(button => {
        buttonsHtml += `<button class="custom-popup-btn ${button.class}" data-action="${button.text.toLowerCase()}">${button.text}</button>`;
      });
      
      const popupHtml = `
        <div class="custom-popup-overlay">
          <div class="custom-popup">
            <div class="custom-popup-header ${headerClass}">
              <h3 class="custom-popup-title">${config.title}</h3>
            </div>
            <div class="custom-popup-content">
              <p class="custom-popup-message">${config.message}</p>
              ${inputHtml}
              <div class="custom-popup-buttons">
                ${buttonsHtml}
              </div>
            </div>
          </div>
        </div>
      `;
      
      $('body').append(popupHtml);
      
      setTimeout(() => {
        $('.custom-popup-overlay').addClass('show');
      }, 10);
      
      $('.custom-popup-overlay').on('click', '.custom-popup-btn', function(e) {
        const action = $(this).data('action');
        const inputValue = $('.custom-popup-input').val();
        
        const button = config.buttons.find(b => b.text.toLowerCase() === action);
        
        if (button && button.callback) {
          const result = button.callback(inputValue);
          if (result === false) {
            return;
          }
        }
        
        CustomPopup.hide();
      });
      
      $('.custom-popup-overlay').on('click', function(e) {
        if (e.target === this) {
          CustomPopup.hide();
        }
      });
      
      $(document).on('keydown.popup', function(e) {
        if (e.keyCode === 27) {
          CustomPopup.hide();
        }
      });
      
      if (config.showInput) {
        setTimeout(() => {
          $('.custom-popup-input').focus();
        }, 350);
      }
    },
    
    hide: function() {
      $('.custom-popup-overlay').removeClass('show');
      setTimeout(() => {
        $('.custom-popup-overlay').remove();
        $(document).off('keydown.popup');
      }, 300);
    },
    
    alert: function(message, title = 'Information', type = 'info') {
      this.show({
        type: type,
        title: title,
        message: message,
        buttons: [
          {
            text: 'OK',
            class: 'custom-popup-btn-primary'
          }
        ]
      });
    },
    
    confirm: function(message, callback, title = 'Confirmation') {
      this.show({
        type: 'info',
        title: title,
        message: message,
        buttons: [
          {
            text: 'Annuler',
            class: 'custom-popup-btn-secondary'
          },
          {
            text: 'Confirmer',
            class: 'custom-popup-btn-primary',
            callback: function() {
              if (callback) callback(true);
            }
          }
        ]
      });
    },
    
    prompt: function(message, callback, defaultValue = '', title = 'Saisie') {
      this.show({
        type: 'info',
        title: title,
        message: message,
        showInput: true,
        inputValue: defaultValue,
        buttons: [
          {
            text: 'Annuler',
            class: 'custom-popup-btn-secondary'
          },
          {
            text: 'Valider',
            class: 'custom-popup-btn-primary',
            callback: function(inputValue) {
              if (callback) callback(inputValue);
            }
          }
        ]
      });
    },
    
    success: function(message, title = 'Succès') {
      this.alert(message, title, 'success');
    },
    
    error: function(message, title = 'Erreur') {
      this.alert(message, title, 'error');
    }
  };
  
  window.customAlert = window.CustomPopup.alert.bind(window.CustomPopup);
  window.customConfirm = window.CustomPopup.confirm.bind(window.CustomPopup);
  window.customPrompt = window.CustomPopup.prompt.bind(window.CustomPopup);

});
