  
  // Refresh transfer history with enhanced display
  function refreshHistory() {
    console.log('📚 Refreshing transfer history...');
    
    $('#history-tbody').html('<tr><td colspan="8" class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Loading transfer history...</td></tr>');
    
    $.post('', { action: 'get_transfer_history' }, function(data) {
      const transfers = JSON.parse(data);
      let html = '';
      
      if (transfers.length === 0) {
        html = '<tr><td colspan="8" class="text-center text-muted">No recent transfers found</td></tr>';
      } else {
        transfers.forEach(function(transfer) {
          const statusBadge = getStatusBadge(transfer.status, transfer.micro_status);
          const createdDate = new Date(transfer.date_created).toLocaleString();
          
          html += `
            <tr>
              <td><strong>#${transfer.transfer_id}</strong></td>
              <td><span class="badge badge-light">${transfer.from_name || transfer.outlet_from || 'Hub'}</span></td>
              <td><span class="badge badge-primary">${transfer.to_name || transfer.outlet_to || 'Unknown'}</span></td>
              <td><span class="badge badge-info">${transfer.product_count || 0}</span></td>
              <td><span class="badge badge-secondary">${transfer.total_quantity || 0}</span></td>
              <td>${statusBadge}</td>
              <td><small>${createdDate}</small></td>
              <td>
                <button class="btn btn-sm btn-info" onclick="viewTransferDetails('${transfer.transfer_id}')" title="View Details">
                  <i class="fa fa-eye"></i>
                </button>
              </td>
            </tr>
          `;
        });
        
        // Update status cards with real data
        const completedToday = transfers.filter(t => {
          const today = new Date().toDateString();
          const transferDate = new Date(t.date_created).toDateString();
          return t.status === 'completed' && transferDate === today;
        }).length;
        
        const activeTransfers = transfers.filter(t => t.status !== 'completed' && t.status !== 'failed').length;
        
        $('#completed-today').text(completedToday);
        $('#active-transfers').text(activeTransfers);
        
        console.log(`✅ Loaded ${transfers.length} transfers (${activeTransfers} active, ${completedToday} completed today)`);
      }
      
      $('#history-tbody').html(html);
    }).fail(function() {
      console.error('❌ Failed to load transfer history');
      $('#history-tbody').html('<tr><td colspan="8" class="text-center text-danger">Failed to load transfer history</td></tr>');
    });
  }
  
  // Get status badge HTML with enhanced styling
  function getStatusBadge(status, microStatus) {
    let badgeClass = 'badge-secondary';
    let icon = 'fa-question';
    let text = status || 'unknown';
    
    switch(status) {
      case 'completed':
        badgeClass = 'badge-success';
        icon = 'fa-check';
        break;
      case 'pending':
        badgeClass = 'badge-warning';
        icon = 'fa-clock';
        break;
      case 'failed':
        badgeClass = 'badge-danger';
        icon = 'fa-times';
        break;
      case 'processing':
        badgeClass = 'badge-info';
        icon = 'fa-spinner fa-spin';
        break;
    }
    
    if (microStatus) {
      text += ` (${microStatus})`;
    }
    
    return `<span class="badge ${badgeClass}"><i class="fa ${icon}"></i> ${text}</span>`;
  }
  
  // Refresh JSON files list with enhanced file browser
  function refreshJsonFiles() {
    console.log('📁 Refreshing JSON files...');
    
    $('#json-files-list').html('<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Loading files...</div>');
    
    $.post('', { action: 'get_json_files' }, function(data) {
      const response = JSON.parse(data);
      let html = '';
      
      if (response.files.length === 0) {
        html = '<div class="text-muted text-center">📄 No JSON files found</div>';
      } else {
        response.files.forEach(function(file) {
          const fileSize = formatFileSize(file.size);
          const modifiedDate = new Date(file.modified * 1000).toLocaleString();
          const fileIcon = getFileIcon(file.name);
          
          html += `
            <div class="file-item" onclick="viewJsonFile('${file.path}')" data-path="${file.path}">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="font-weight-bold">
                    <i class="fa ${fileIcon}"></i> ${file.name}
                  </div>
                  <div class="small text-info">${file.path}</div>
                </div>
                <span class="badge badge-light">${fileSize}</span>
              </div>
              <div class="small text-muted mt-1">
                <i class="fa fa-clock"></i> ${modifiedDate}
              </div>
            </div>
          `;
        });
        
        console.log(`✅ Found ${response.files.length} JSON files`);
      }
      
      $('#json-files-list').html(html);
    }).fail(function() {
      console.error('❌ Failed to load JSON files');
      $('#json-files-list').html('<div class="text-danger text-center">❌ Failed to load files</div>');
    });
  }
  
  // Get file icon based on file type
  function getFileIcon(filename) {
    if (filename.includes('LAST_RUN')) return 'fa-play-circle text-success';
    if (filename.includes('log')) return 'fa-file-alt text-warning';
    if (filename.includes('result')) return 'fa-chart-bar text-info';
    if (filename.includes('settings')) return 'fa-cogs text-primary';
    return 'fa-file-code text-secondary';
  }
  
  // View JSON file content with syntax highlighting
  function viewJsonFile(filePath) {
    console.log('👁️ Viewing file:', filePath);
    
    // Update selection
    $('.file-item').removeClass('selected');
    $(`.file-item[data-path="${filePath}"]`).addClass('selected');
    
    currentFile = filePath;
    
    $('#file-viewer').html('<div class="text-center mt-5"><div class="spinner-border" role="status"></div><br>Loading file...</div>');
    
    $.post('', { 
      action: 'view_json_file',
      file_path: filePath 
    }, function(data) {
      const response = JSON.parse(data);
      
      if (response.success) {
        let content = response.content;
        
        // Try to format JSON if it's valid
        if (response.parsed) {
          try {
            content = JSON.stringify(response.parsed, null, 2);
          } catch (e) {
            console.warn('Could not format JSON:', e);
          }
        }
        
        $('#file-viewer').html(`
          <div class="mb-3 p-2 bg-light rounded">
            <strong><i class="fa fa-file"></i> ${response.file_info.path}</strong><br>
            <small class="text-muted">
              📏 Size: ${formatFileSize(response.file_info.size)} | 
              🕐 Modified: ${new Date(response.file_info.modified).toLocaleString()}
            </small>
          </div>
          <hr>
          <pre style="white-space: pre-wrap; margin: 0; font-size: 11px; line-height: 1.4;">${content}</pre>
        `);
        
        console.log('✅ File loaded successfully');
      } else {
        $('#file-viewer').html(`
          <div class="text-danger text-center mt-5">
            <i class="fa fa-exclamation-triangle fa-2x mb-2"></i><br>
            <strong>Error loading file</strong><br>
            <small>${response.error}</small>
          </div>
        `);
        console.error('❌ File load error:', response.error);
      }
    }).fail(function() {
      $('#file-viewer').html(`
        <div class="text-danger text-center mt-5">
          <i class="fa fa-times fa-2x mb-2"></i><br>
          <strong>Request failed</strong><br>
          <small>Could not load file</small>
        </div>
      `);
    });
  }
  
  // Format file size helper
  function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }
  
  // Format JSON in viewer with error handling
  function formatJson() {
    if (!currentFile) {
      alert('No file selected');
      return;
    }
    
    console.log('🎨 Formatting JSON for:', currentFile);
    
    $.post('', {
      action: 'view_json_file',
      file_path: currentFile
    }, function(data) {
      const response = JSON.parse(data);
      
      if (response.success && response.parsed) {
        try {
          const formatted = JSON.stringify(response.parsed, null, 2);
          $('#file-viewer pre').text(formatted);
          console.log('✅ JSON formatted successfully');
        } catch (e) {
          alert('Could not format JSON: ' + e.message);
        }
      } else {
        alert('File is not valid JSON or could not be parsed');
      }
    });
  }
  
  // Download file functionality
  function downloadFile() {
    if (!currentFile) {
      alert('No file selected');
      return;
    }
    
    console.log('💾 Downloading file:', currentFile);
    
    // Create download link
    const link = document.createElement('a');
    link.href = currentFile;
    link.download = currentFile.split('/').pop();
    link.click();
  }
  
  // Auto-save settings helper
  function autoSaveSettings() {
    console.log('💾 Auto-saving settings...');
    saveSettings(true);
  }
