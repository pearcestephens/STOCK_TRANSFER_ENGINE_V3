  <!-- ######### JAVASCRIPT BEGINS HERE ######### -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
  // Global variables for Transfer Control Center
  let currentFile = null;
  let refreshInterval = null;
  let progressInterval = null;
  let dashboardSettings = {};
  
  $(document).ready(function() {
    console.log('🎯 Transfer Control Center initializing...');
    
    // Initialize dashboard
    loadOutlets();
    loadSettings();
    refreshHistory();
    refreshJsonFiles();
    updateStatus();
    loadNeuralBrainStats();
    
    // Set up auto-refresh every 15 seconds
    refreshInterval = setInterval(function() {
      updateStatus();
      loadNeuralBrainStats();
      if ($('#monitor-tab').hasClass('active')) {
        updateAnalytics();
      }
    }, 15000);
    
    // Transfer form submission
    $('#transfer-form').on('submit', function(e) {
      e.preventDefault();
      executeTransfer();
    });
    
    // Clear output button
    $('#clear-output').on('click', function() {
      $('#transfer-output').html('<div class="text-center text-muted mt-5">Output cleared...<br><small>Ready for next execution</small></div>');
    });
    
    // Tab switching events
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
      const target = $(e.target).attr('href');
      console.log('📑 Switching to tab:', target);
      
      switch(target) {
        case '#files':
          refreshJsonFiles();
          break;
        case '#history':
          refreshHistory();
          break;
        case '#monitor':
          loadAnalytics();
          break;
        case '#schedule':
          updateCronStatus();
          break;
      }
    });
    
    // Settings auto-save on change
    $('#settings input, #settings select').on('change', function() {
      setTimeout(autoSaveSettings, 1000); // Auto-save after 1 second
    });
    
    console.log('✅ Transfer Control Center initialized successfully');
  });
  
  // Load outlets for dropdown
  function loadOutlets() {
    console.log('🏪 Loading outlets...');
    
    $.post('', { action: 'get_outlets' }, function(data) {
      const outlets = JSON.parse(data);
      let html = '<option value="">Select an outlet...</option>';
      
      outlets.forEach(function(outlet) {
        html += `<option value="${outlet.id}">${outlet.name}</option>`;
      });
      
      $('#target-outlet').html(html);
      $('#auto-balance-outlets').html('<option value="all">All Outlets</option>' + html);
      
      console.log(`✅ Loaded ${outlets.length} outlets`);
    }).fail(function() {
      console.error('❌ Failed to load outlets');
      $('#target-outlet').html('<option value="">Failed to load outlets</option>');
    });
  }
  
  // Execute transfer with enhanced progress tracking
  function executeTransfer() {
    const mode = $('#transfer-mode').val();
    const targetOutlet = $('#target-outlet').val();
    const simulate = $('#simulate-mode').is(':checked');
    
    if (!targetOutlet) {
      alert('Please select a target outlet');
      return;
    }
    
    console.log(`🚀 Executing transfer: ${mode} -> ${targetOutlet} (simulate: ${simulate})`);
    
    // Show progress section with animation
    $('#progress-section').slideDown();
    $('#progress-bar').css('width', '10%').removeClass('bg-danger bg-success').addClass('progress-bar-striped progress-bar-animated');
    $('#progress-text').text('Initializing transfer...');
    $('#system-status').text('Running').addClass('status-running');
    
    // Update output with loading state
    $('#transfer-output').html(`
      <div class="text-success">🚀 Starting transfer execution...</div>
      <div class="text-info">Mode: ${mode}</div>
      <div class="text-info">Target: ${$('#target-outlet option:selected').text()}</div>
      <div class="text-muted">Simulate: ${simulate ? 'Yes' : 'No'}</div>
      <hr>
      <div class="text-warning">⏳ Initializing Neural Brain...</div>
    `);
    
    const options = {
      min_source_stock: $('#min-source-stock').val(),
      max_contribution_per_store: $('#max-contribution').val(),
      buffer_percentage: $('#buffer-percentage').val(),
      respect_pack_outers: $('#respect-pack-outers').is(':checked'),
      balance_categories: $('#balance-categories').is(':checked'),
      minimum_transfer_value: $('#min-transfer-value').val(),
      shipping_weight_threshold: $('#weight-threshold').val()
    };
    
    // Simulate progress updates
    let progress = 10;
    progressInterval = setInterval(function() {
      if (progress < 90) {
        progress += Math.random() * 15;
        $('#progress-bar').css('width', progress + '%');
        
        if (progress > 30 && progress < 50) {
          $('#progress-text').text('Analyzing source outlets...');
        } else if (progress > 50 && progress < 70) {
          $('#progress-text').text('Processing Neural Brain recommendations...');
        } else if (progress > 70) {
          $('#progress-text').text('Generating transfer plan...');
        }
      }
    }, 800);
    
    $.post('', {
      action: 'run_transfer',
      mode: mode,
      target_outlet: targetOutlet,
      simulate: simulate ? 1 : 0,
      options: JSON.stringify(options)
    }, function(response) {
      clearInterval(progressInterval);
      
      const data = JSON.parse(response);
      
      if (data.success) {
        $('#progress-bar').css('width', '100%').removeClass('progress-bar-striped progress-bar-animated').addClass('bg-success');
        $('#progress-text').text('✅ Transfer completed successfully');
        $('#system-status').text('Complete').removeClass('status-running');
        
        // Enhanced output display
        $('#transfer-output').html(`
          <div class="text-success">✅ Transfer execution completed successfully!</div>
          <div class="text-info">📋 Command: ${data.command}</div>
          <div class="text-muted">🕐 Timestamp: ${new Date(data.timestamp).toLocaleString()}</div>
          <hr>
          <div class="text-primary">📊 Output:</div>
          <div style="white-space: pre-wrap; background: rgba(40,167,69,0.1); padding: 10px; border-radius: 4px; border-left: 4px solid #28a745;">${data.output}</div>
        `);
        
        // Auto-refresh history and analytics
        setTimeout(function() {
          refreshHistory();
          updateAnalytics();
          loadNeuralBrainStats();
        }, 2000);
        
      } else {
        $('#progress-bar').addClass('bg-danger').css('width', '100%').removeClass('progress-bar-striped progress-bar-animated');
        $('#progress-text').text('❌ Transfer failed');
        $('#system-status').text('Error').removeClass('status-running');
        $('#transfer-output').html(`
          <div class="text-danger">❌ Transfer failed!</div>
          <div class="text-muted">Error: ${data.error || 'Unknown error'}</div>
        `);
      }
    }).fail(function(xhr, status, error) {
      clearInterval(progressInterval);
      $('#progress-bar').addClass('bg-danger').css('width', '100%').removeClass('progress-bar-striped progress-bar-animated');
      $('#progress-text').text('❌ Request failed');
      $('#system-status').text('Error').removeClass('status-running');
      $('#transfer-output').html(`
        <div class="text-danger">❌ Request failed!</div>
        <div class="text-muted">Status: ${status}</div>
        <div class="text-muted">Error: ${error}</div>
      `);
    });
  }
  
  // Update system status with enhanced monitoring
  function updateStatus() {
    $.post('', { action: 'get_progress' }, function(data) {
      const status = JSON.parse(data);
      
      if (status.is_running) {
        $('#system-status').text('Running').addClass('status-running badge-warning').removeClass('badge-secondary badge-success');
      } else {
        $('#system-status').text('Idle').removeClass('status-running badge-warning').addClass('badge-secondary');
      }
      
      // Update latest log if available and no current activity
      if (status.latest_log && status.latest_log.trim()) {
        const currentOutput = $('#transfer-output').text();
        if (currentOutput.includes('Ready to execute') || currentOutput.includes('Output cleared')) {
          $('#transfer-output').html(`
            <div class="text-info">📋 Latest system activity:</div>
            <div class="text-muted">🕐 Updated: ${new Date().toLocaleString()}</div>
            <hr>
            <div style="white-space: pre-wrap; background: rgba(23,162,184,0.1); padding: 10px; border-radius: 4px; border-left: 4px solid #17a2b8;">${status.latest_log}</div>
          `);
        }
      }
    });
  }
  
  // Load Neural Brain statistics
  function loadNeuralBrainStats() {
    $.post('', { action: 'get_neural_brain_stats' }, function(data) {
      const stats = JSON.parse(data);
      
      $('#neural-memories').text(stats.memory_count || 0);
      $('#total-memories').text(stats.memory_count || 0);
      $('#success-rate').text(stats.success_rate + '%').removeClass('badge-danger badge-warning').addClass(
        stats.success_rate >= 90 ? 'badge-success' : 
        stats.success_rate >= 70 ? 'badge-warning' : 'badge-danger'
      );
      
      // Update analytics cards
      $('#analytics-success-rate').text(stats.success_rate + '%');
      $('#analytics-total-transfers').text(stats.total_transfers || 0);
      $('#analytics-memories').text(stats.memory_count || 0);
      
      // Update learning progress bar
      const learningProgress = Math.min((stats.memory_count || 0) / 100 * 100, 100);
      $('#learning-progress').css('width', learningProgress + '%');
      
      // Update neural status
      if (stats.memory_count > 50) {
        $('#neural-status').text('Learning').removeClass('badge-secondary badge-warning').addClass('badge-success');
        $('#learning-trend').text('Improving').removeClass('badge-secondary').addClass('badge-success');
      } else if (stats.memory_count > 10) {
        $('#neural-status').text('Training').removeClass('badge-secondary badge-success').addClass('badge-warning');
        $('#learning-trend').text('Stable').removeClass('badge-secondary').addClass('badge-info');
      } else {
        $('#neural-status').text('Initializing').removeClass('badge-success badge-warning').addClass('badge-secondary');
      }
      
    }).fail(function() {
      console.warn('⚠️ Could not load Neural Brain stats');
    });
  }
