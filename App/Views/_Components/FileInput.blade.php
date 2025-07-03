<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 *
 * $name (string) - Input name
 * $fieldname (string) - Display label
 * $placeholder (string) optional - Placeholder text
 * $id (string) optional - Input ID
 * $value (string) optional - Current value
 * $validation (string) optional - Validation rule
 * $validationfail (string) optional - Validation error message
 * $allowed_types (array) optional - Allowed file types
 * $max_size (int) optional - Maximum file size in bytes
 * $multiple (bool) optional - Allow multiple files
 * $required (bool) optional - Required field
 * $accept (string) optional - Accept attribute
 * $help_text (string) optional - Help text
 * $upload_result (array) optional - Upload result from server
 */
?>

<div class="form-group">
  @if($fieldname)
    <label for="{{$id or $name}}" class="form-label">
      {{ \Core\Helpers\Helper::e($fieldname) }}
      @if(isset($required) && $required)
        <span class="text-danger">*</span>
      @endif
    </label>
  @endif

  <div class="file-input-wrapper" id="file-wrapper-{{$id or $name}}">
    <!-- Hidden file input -->
    <input 
      type="file" 
      id="{{$id or $name}}" 
      name="{{$name}}{{ isset($multiple) && $multiple ? '[]' : '' }}"
      class="form-control file-input-hidden"
      @if(isset($accept)) accept="{{ \Core\Helpers\Helper::e($accept) }}" @endif
      @if(isset($multiple) && $multiple) multiple @endif
      @if(isset($required) && $required) required @endif
      @if(isset($validation)) validation="{{ \Core\Helpers\Helper::e($validation) }}" @endif
      @if(isset($validationfail)) validation-fail="{{ \Core\Helpers\Helper::e($validationfail) }}" @endif
      data-max-size="{{ $max_size ?? 5242880 }}"
      data-allowed-types="{{ json_encode($allowed_types ?? ['document', 'image']) }}"
      style="display: none;"
    />

    <!-- Custom file input interface -->
    <div class="custom-file-input">
      <div class="file-input-display">
        <i class="fas fa-cloud-upload-alt"></i>
        <span class="file-input-text">{{ \Core\Helpers\Helper::e($placeholder ?? 'Choose file...') }}</span>
        <span class="file-input-info">
          Max size: {{ isset($max_size) ? \Core\Helpers\Helper::formatBytes($max_size) : '5 MB' }}
        </span>
      </div>
      <button type="button" class="btn btn-outline-primary file-input-button">
        Browse Files
      </button>
    </div>

    <!-- File preview area -->
    <div class="file-preview-area" style="display: none;">
      <div class="file-preview-list"></div>
      <div class="file-upload-progress" style="display: none;">
        <div class="progress">
          <div class="progress-bar" role="progressbar" style="width: 0%"></div>
        </div>
      </div>
    </div>
  </div>

  @if(isset($help_text))
    <small class="form-text text-muted">{{ \Core\Helpers\Helper::e($help_text) }}</small>
  @endif

  @if(isset($upload_result) && $upload_result)
    @if($upload_result['valid'])
      @component('_Components.Notification', array(
        'type' => 'success',
        'title' => 'File Upload Successful!',
        'message' => 'File uploaded successfully: ' . $upload_result['original_name'] . ' (' . number_format($upload_result['size'] / 1024, 2) . ' KB)'
      ))
      @endcomponent
    @else
      @component('_Components.Notification', array(
        'type' => 'error',
        'title' => 'File Upload Failed',
        'message' => implode(', ', $upload_result['errors'])
      ))
      @endcomponent
    @endif
  @endif
</div>

<style>
.file-input-wrapper {
  border: 2px dashed #ddd;
  border-radius: 8px;
  padding: 20px;
  text-align: center;
  transition: all 0.3s ease;
  margin-bottom: 15px;
}

.file-input-wrapper.dragover {
  border-color: #007bff;
  background-color: #f8f9fa;
}

.custom-file-input {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 15px;
}

.file-input-display {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.file-input-display i {
  font-size: 3rem;
  color: #6c757d;
}

.file-input-text {
  font-size: 1.1rem;
  color: #495057;
}

.file-input-info {
  font-size: 0.9rem;
  color: #6c757d;
}

.file-input-button {
  padding: 10px 20px;
  border-radius: 6px;
}

.file-preview-area {
  margin-top: 20px;
  border-top: 1px solid #dee2e6;
  padding-top: 20px;
}

.file-preview-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px;
  background-color: #f8f9fa;
  border-radius: 6px;
  margin-bottom: 10px;
}

.file-preview-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.file-preview-info i {
  color: #6c757d;
}

.file-name {
  font-weight: 500;
}

.file-size {
  color: #6c757d;
  font-size: 0.9rem;
}

.file-error {
  margin-bottom: 10px;
}

.file-upload-progress {
  margin-top: 15px;
}

.progress {
  height: 8px;
  border-radius: 4px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const fileInput = document.getElementById('{{$id or $name}}');
  const wrapper = document.getElementById('file-wrapper-{{$id or $name}}');
  const display = wrapper.querySelector('.file-input-display');
  const button = wrapper.querySelector('.file-input-button');
  const previewArea = wrapper.querySelector('.file-preview-area');
  const previewList = wrapper.querySelector('.file-preview-list');
  const progressArea = wrapper.querySelector('.file-upload-progress');
  const progressBar = wrapper.querySelector('.progress-bar');
  
  const maxSize = parseInt(fileInput.dataset.maxSize);
  const allowedTypes = JSON.parse(fileInput.dataset.allowedTypes);
  
  // Trigger file input when button is clicked
  button.addEventListener('click', function() {
    fileInput.click();
  });
  
  // Handle file selection
  fileInput.addEventListener('change', function(e) {
    const files = Array.from(e.target.files);
    
    if (files.length === 0) return;
    
    // Clear previous previews
    previewList.innerHTML = '';
    
    // Validate and preview files
    files.forEach((file, index) => {
      const validation = validateFile(file, maxSize, allowedTypes);
      
      if (validation.valid) {
        addFilePreview(file, index, validation);
      } else {
        showFileError(file, validation.errors);
      }
    });
    
    // Show preview area if files are valid
    if (previewList.children.length > 0) {
      previewArea.style.display = 'block';
    }
  });
  
  // Drag and drop functionality
  wrapper.addEventListener('dragover', function(e) {
    e.preventDefault();
    wrapper.classList.add('dragover');
  });
  
  wrapper.addEventListener('dragleave', function(e) {
    e.preventDefault();
    wrapper.classList.remove('dragover');
  });
  
  wrapper.addEventListener('drop', function(e) {
    e.preventDefault();
    wrapper.classList.remove('dragover');
    
    const files = Array.from(e.dataTransfer.files);
    fileInput.files = e.dataTransfer.files;
    
    // Trigger change event
    const event = new Event('change', { bubbles: true });
    fileInput.dispatchEvent(event);
  });
  
  function validateFile(file, maxSize, allowedTypes) {
    const errors = [];
    
    // Check file size
    if (file.size > maxSize) {
      errors.push('File size exceeds maximum allowed size');
    }
    
    // Check file type (basic check - server-side validation is more important)
    const fileExtension = file.name.split('.').pop().toLowerCase();
    const allowedExtensions = getAllowedExtensions(allowedTypes);
    
    if (!allowedExtensions.includes(fileExtension)) {
      errors.push('File type not allowed');
    }
    
    return {
      valid: errors.length === 0,
      errors: errors
    };
  }
  
  function getAllowedExtensions(allowedTypes) {
    const extensions = [];
    allowedTypes.forEach(type => {
      switch(type) {
        case 'image':
          extensions.push('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg');
          break;
        case 'document':
          extensions.push('pdf', 'doc', 'docx', 'txt', 'rtf');
          break;
        case 'spreadsheet':
          extensions.push('xls', 'xlsx', 'csv');
          break;
        case 'presentation':
          extensions.push('ppt', 'pptx');
          break;
        case 'archive':
          extensions.push('zip', 'rar', '7z', 'tar', 'gz');
          break;
        case 'video':
          extensions.push('mp4', 'avi', 'mov', 'wmv', 'flv', 'webm');
          break;
        case 'audio':
          extensions.push('mp3', 'wav', 'ogg', 'aac', 'flac');
          break;
      }
    });
    return extensions;
  }
  
  function addFilePreview(file, index, validation) {
    const preview = document.createElement('div');
    preview.className = 'file-preview-item';
    preview.innerHTML = `
      <div class='file-preview-info'>
        <i class='fas fa-file'></i>
        <span class='file-name'>${file.name}</span>
        <span class='file-size'>(${formatFileSize(file.size)})</span>
      </div>
      <button type='button' class='btn btn-sm btn-outline-danger remove-file' data-index='${index}'>
        <i class='fas fa-times'></i>
      </button>
    `;
    
    previewList.appendChild(preview);
    
    // Add remove functionality
    preview.querySelector('.remove-file').addEventListener('click', function() {
      preview.remove();
      if (previewList.children.length === 0) {
        previewArea.style.display = 'none';
      }
    });
  }
  
  function showFileError(file, errors) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'file-error';
    errorDiv.innerHTML = `
      <div class='alert alert-danger'>
        <strong>${file.name}:</strong> ${errors.join(', ')}
      </div>
    `;
    previewList.appendChild(errorDiv);
  }
  
  function formatFileSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;
    
    while (size >= 1024 && unitIndex < units.length - 1) {
      size /= 1024;
      unitIndex++;
    }
    
    return size.toFixed(2) + ' ' + units[unitIndex];
  }
});
</script> 