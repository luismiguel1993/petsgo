import React, { useState, useRef, useCallback } from 'react';
import { Upload, X, FileText, CheckCircle2, AlertCircle, Loader2, ZoomIn } from 'lucide-react';

/**
 * FileUploadBox — Componente reutilizable de carga de archivos.
 * Diseñado para funcionar en desktop y mobile (web, Android/iOS futuro).
 *
 * @param {function} onUpload - async (file: File) => void — se llama al confirmar upload
 * @param {string}   accept   - tipos de archivo aceptados (default: imágenes + PDF)
 * @param {number}   maxSizeMB - tamaño máximo en MB (default: 10)
 * @param {string}   label     - texto principal del dropzone
 * @param {string}   hint      - texto secundario (ej: "JPG, PNG o PDF — Max 10MB")
 * @param {boolean}  disabled  - deshabilita toda interacción
 * @param {string}   existingUrl - URL del archivo ya subido (para mostrar preview / link)
 * @param {string}   existingName - nombre del archivo ya subido
 * @param {string}   status   - 'idle' | 'uploading' | 'success' | 'error'
 * @param {number}   progress - 0-100 para barra de progreso (opcional)
 * @param {string}   errorMsg - mensaje de error a mostrar
 */
const FileUploadBox = ({
  onUpload,
  accept = '.jpg,.jpeg,.png,.webp,.pdf',
  maxSizeMB = 10,
  label = 'Seleccionar archivo',
  hint,
  disabled = false,
  existingUrl = '',
  existingName = '',
  status: externalStatus,
  progress: externalProgress,
  errorMsg: externalError,
}) => {
  const inputRef = useRef(null);
  const [dragOver, setDragOver] = useState(false);
  const [showLightbox, setShowLightbox] = useState(false);
  const [selectedFile, setSelectedFile] = useState(null);
  const [preview, setPreview] = useState(existingUrl || '');
  const [internalStatus, setInternalStatus] = useState(existingUrl ? 'success' : 'idle'); // idle | selected | uploading | success | error
  const [internalProgress, setInternalProgress] = useState(0);
  const [internalError, setInternalError] = useState('');

  const status = externalStatus || internalStatus;
  const progress = externalProgress ?? internalProgress;
  const errorMessage = externalError || internalError;

  const isUploading = status === 'uploading';
  const isDisabled = disabled || isUploading;

  const isImageFile = (file) => file?.type?.startsWith('image/');

  const generatePreview = useCallback((file) => {
    if (isImageFile(file)) {
      const reader = new FileReader();
      reader.onload = (e) => setPreview(e.target.result);
      reader.readAsDataURL(file);
    } else {
      setPreview('');
    }
  }, []);

  const validateFile = (file) => {
    if (!file) return 'No se seleccionó archivo';
    const maxBytes = maxSizeMB * 1024 * 1024;
    if (file.size > maxBytes) {
      const fileSizeMB = (file.size / (1024 * 1024)).toFixed(1);
      return `El archivo pesa ${fileSizeMB}MB y el límite es ${maxSizeMB}MB. Elige un archivo más liviano.`;
    }
    const acceptList = accept.split(',').map(a => a.trim().toLowerCase());
    const ext = '.' + file.name.split('.').pop().toLowerCase();
    const matchExt = acceptList.some(a => a === ext);
    const matchMime = acceptList.some(a => file.type && file.type.includes(a.replace('.', '')));
    if (!matchExt && !matchMime) return `Formato no permitido. Usa: ${accept}`;
    return null;
  };

  const handleFileSelect = (file) => {
    setInternalError('');
    const err = validateFile(file);
    if (err) {
      setInternalError(err);
      setInternalStatus('error');
      return;
    }
    setSelectedFile(file);
    generatePreview(file);
    setInternalStatus('selected');
  };

  const handleInputChange = (e) => {
    const file = e.target.files?.[0];
    if (file) handleFileSelect(file);
  };

  const handleDrop = (e) => {
    e.preventDefault();
    setDragOver(false);
    if (isDisabled) return;
    const file = e.dataTransfer?.files?.[0];
    if (file) handleFileSelect(file);
  };

  const handleDragOver = (e) => {
    e.preventDefault();
    if (!isDisabled) setDragOver(true);
  };

  const handleDragLeave = () => setDragOver(false);

  const handleUpload = async () => {
    if (!selectedFile || !onUpload) return;
    setInternalStatus('uploading');
    setInternalProgress(0);
    setInternalError('');

    // Simular progreso visual mientras sube
    const progressInterval = setInterval(() => {
      setInternalProgress(prev => {
        if (prev >= 90) { clearInterval(progressInterval); return 90; }
        return prev + Math.random() * 15;
      });
    }, 200);

    try {
      await onUpload(selectedFile);
      clearInterval(progressInterval);
      setInternalProgress(100);
      setInternalStatus('success');
    } catch (err) {
      clearInterval(progressInterval);
      setInternalProgress(0);
      setInternalError(err?.message || 'Error al subir archivo');
      setInternalStatus('error');
    }
  };

  const handleRemove = () => {
    setSelectedFile(null);
    setPreview(existingUrl || '');
    setInternalStatus(existingUrl ? 'success' : 'idle');
    setInternalProgress(0);
    setInternalError('');
    if (inputRef.current) inputRef.current.value = '';
  };

  const openFilePicker = () => {
    if (!isDisabled && inputRef.current) inputRef.current.click();
  };

  const formatFileSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  };

  /* ───── Lightbox ───── */
  const Lightbox = () => {
    if (!showLightbox) return null;
    const imgSrc = preview || existingUrl;
    if (!imgSrc) return null;
    return (
      <div
        onClick={() => setShowLightbox(false)}
        style={styles.lightboxBackdrop}
      >
        <div style={styles.lightboxContent} onClick={(e) => e.stopPropagation()}>
          <img src={imgSrc} alt="Vista previa" style={styles.lightboxImage} />
          <button
            onClick={() => setShowLightbox(false)}
            style={styles.lightboxClose}
            type="button"
            aria-label="Cerrar vista previa"
          >
            <X size={22} />
          </button>
        </div>
      </div>
    );
  };

  const handleThumbClick = (e) => {
    e.stopPropagation();
    if (preview || existingUrl) setShowLightbox(true);
  };

  /* ───── Render ───── */
  const defaultHint = hint || `JPG, PNG, WebP o PDF — Máx ${maxSizeMB}MB`;

  // Si hay un archivo subido exitosamente y tenemos preview de imagen
  if (status === 'success' && (preview || existingUrl)) {
    const imgSrc = preview || existingUrl;
    const isImg = imgSrc && !imgSrc.endsWith('.pdf');
    return (
      <>
        <Lightbox />
        <div style={styles.successContainer}>
          {isImg ? (
            <div style={{ ...styles.previewWrapper, cursor: 'pointer' }} onClick={handleThumbClick} title="Ver imagen completa">
              <img src={imgSrc} alt="Preview" style={styles.previewImage} />
              <div style={styles.previewOverlay}>
                <ZoomIn size={16} color="#fff" />
              </div>
            </div>
          ) : (
            <div style={styles.pdfPreview}>
              <FileText size={32} color="#F97316" />
              <span style={styles.pdfName}>{selectedFile?.name || existingName || 'Documento'}</span>
              <CheckCircle2 size={18} color="#22C55E" />
            </div>
          )}
          <div style={styles.successInfo}>
            <div style={styles.successBadge}>
              <CheckCircle2 size={14} color="#22C55E" />
              <span style={{ color: '#16a34a', fontWeight: 700, fontSize: 13 }}>Subido correctamente</span>
            </div>
            {selectedFile && (
              <span style={{ fontSize: 11, color: '#9ca3af' }}>{selectedFile.name} · {formatFileSize(selectedFile.size)}</span>
            )}
            {!disabled && (
              <button
                onClick={handleRemove}
                style={styles.changeBtn}
                type="button"
              >
                Cambiar archivo
              </button>
            )}
          </div>
        </div>
      </>
    );
  }

  // Si hay archivo seleccionado pero no subido
  if (status === 'selected' && selectedFile) {
    const isImg = isImageFile(selectedFile);
    return (
      <>
        <Lightbox />
        <div style={styles.selectedContainer}>
          <div style={styles.selectedFileRow}>
            {isImg && preview ? (
              <img src={preview} alt="Preview" style={{ ...styles.thumbPreview, cursor: 'pointer' }} onClick={handleThumbClick} title="Ver imagen completa" />
            ) : (
            <div style={styles.fileIconBox}>
              <FileText size={24} color="#F97316" />
            </div>
          )}
          <div style={styles.fileInfo}>
            <span style={styles.fileName}>{selectedFile.name}</span>
            <span style={styles.fileMeta}>{formatFileSize(selectedFile.size)}</span>
          </div>
          <button onClick={handleRemove} style={styles.removeBtn} type="button" aria-label="Quitar archivo">
            <X size={18} />
          </button>
        </div>
          <button
            onClick={handleUpload}
            disabled={isUploading}
            style={styles.uploadBtn}
            type="button"
          >
            <Upload size={16} />
            Subir archivo
          </button>
        </div>
      </>
    );
  }

  // Estado de carga
  if (status === 'uploading') {
    return (
      <div style={styles.uploadingContainer}>
        <div style={styles.uploadingContent}>
          {preview && isImageFile(selectedFile) ? (
            <img src={preview} alt="Subiendo..." style={{ ...styles.thumbPreview, opacity: 0.6 }} />
          ) : (
            <div style={{ ...styles.fileIconBox, opacity: 0.6 }}>
              <FileText size={24} color="#F97316" />
            </div>
          )}
          <div style={styles.fileInfo}>
            <span style={styles.fileName}>{selectedFile?.name || 'Subiendo...'}</span>
            <div style={styles.progressBarBg}>
              <div style={{ ...styles.progressBarFill, width: `${Math.min(progress, 100)}%` }} />
            </div>
            <span style={styles.fileMeta}>
              <Loader2 size={12} style={{ animation: 'spin 1s linear infinite' }} />
              {' '}Subiendo... {Math.round(progress)}%
            </span>
          </div>
        </div>
      </div>
    );
  }

  // Estado de error con archivo
  if (status === 'error' && errorMessage) {
    return (
      <div style={styles.errorContainer}>
        <div style={styles.errorContent}>
          <AlertCircle size={20} color="#dc2626" />
          <span style={styles.errorText}>{errorMessage}</span>
        </div>
        <button onClick={handleRemove} style={styles.retryBtn} type="button">
          Intentar de nuevo
        </button>
      </div>
    );
  }

  // Dropzone idle — estado principal
  return (
    <div
      onClick={openFilePicker}
      onDrop={handleDrop}
      onDragOver={handleDragOver}
      onDragLeave={handleDragLeave}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') openFilePicker(); }}
      aria-label={label}
      style={{
        ...styles.dropzone,
        borderColor: dragOver ? '#F97316' : '#e5e7eb',
        background: dragOver ? '#FFF7ED' : isDisabled ? '#f9fafb' : '#fff',
        cursor: isDisabled ? 'not-allowed' : 'pointer',
        opacity: isDisabled ? 0.5 : 1,
      }}
    >
      <input
        ref={inputRef}
        type="file"
        accept={accept}
        onChange={handleInputChange}
        disabled={isDisabled}
        style={{ display: 'none' }}
      />
      <div style={styles.dropzoneIcon}>
        <Upload size={28} color={dragOver ? '#F97316' : '#9ca3af'} />
      </div>
      <span style={styles.dropzoneLabel}>{label}</span>
      <span style={styles.dropzoneHint}>{defaultHint}</span>
      <div style={styles.dropzoneBtnFake}>
        Seleccionar archivo
      </div>
    </div>
  );
};

/* ───── Keyframes for spinner (injected once) ───── */
if (typeof document !== 'undefined' && !document.getElementById('fileupload-spin-style')) {
  const style = document.createElement('style');
  style.id = 'fileupload-spin-style';
  style.textContent = `@keyframes spin { to { transform: rotate(360deg); } }`;
  document.head.appendChild(style);
}

/* ───── Styles ───── */
const styles = {
  /* Dropzone (idle) */
  dropzone: {
    border: '2px dashed #e5e7eb',
    borderRadius: 14,
    padding: '24px 16px',
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    gap: 6,
    transition: 'all 0.2s ease',
    WebkitTapHighlightColor: 'transparent',
    userSelect: 'none',
  },
  dropzoneIcon: {
    width: 52,
    height: 52,
    borderRadius: 14,
    background: '#f3f4f6',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 4,
  },
  dropzoneLabel: {
    fontSize: 14,
    fontWeight: 700,
    color: '#374151',
    textAlign: 'center',
  },
  dropzoneHint: {
    fontSize: 12,
    color: '#9ca3af',
    textAlign: 'center',
  },
  dropzoneBtnFake: {
    marginTop: 8,
    padding: '8px 24px',
    borderRadius: 10,
    background: '#FFF7ED',
    color: '#F97316',
    fontWeight: 700,
    fontSize: 13,
    border: '1px solid #FDBA74',
    pointerEvents: 'none',
  },

  /* Selected (pre-upload) */
  selectedContainer: {
    border: '2px solid #FDBA74',
    borderRadius: 14,
    padding: 14,
    background: '#FFFBF5',
    display: 'flex',
    flexDirection: 'column',
    gap: 12,
  },
  selectedFileRow: {
    display: 'flex',
    alignItems: 'center',
    gap: 12,
  },
  thumbPreview: {
    width: 56,
    height: 56,
    borderRadius: 10,
    objectFit: 'cover',
    border: '1px solid #e5e7eb',
    flexShrink: 0,
  },
  fileIconBox: {
    width: 56,
    height: 56,
    borderRadius: 10,
    background: '#FFF7ED',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    flexShrink: 0,
  },
  fileInfo: {
    flex: 1,
    minWidth: 0,
    display: 'flex',
    flexDirection: 'column',
    gap: 4,
  },
  fileName: {
    fontSize: 13,
    fontWeight: 600,
    color: '#374151',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
  },
  fileMeta: {
    fontSize: 11,
    color: '#9ca3af',
    display: 'flex',
    alignItems: 'center',
    gap: 4,
  },
  removeBtn: {
    width: 32,
    height: 32,
    borderRadius: '50%',
    border: 'none',
    background: '#f3f4f6',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    color: '#6b7280',
    flexShrink: 0,
  },
  uploadBtn: {
    padding: '10px 20px',
    borderRadius: 10,
    background: '#F97316',
    color: '#fff',
    fontWeight: 700,
    fontSize: 14,
    border: 'none',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    width: '100%',
  },

  /* Uploading */
  uploadingContainer: {
    border: '2px solid #FDBA74',
    borderRadius: 14,
    padding: 14,
    background: '#FFFBF5',
  },
  uploadingContent: {
    display: 'flex',
    alignItems: 'center',
    gap: 12,
  },
  progressBarBg: {
    width: '100%',
    height: 6,
    borderRadius: 3,
    background: '#f3f4f6',
    overflow: 'hidden',
  },
  progressBarFill: {
    height: '100%',
    borderRadius: 3,
    background: 'linear-gradient(90deg, #F97316, #FB923C)',
    transition: 'width 0.3s ease',
  },

  /* Success */
  successContainer: {
    border: '2px solid #86efac',
    borderRadius: 14,
    padding: 14,
    background: '#f0fdf4',
    display: 'flex',
    gap: 14,
    alignItems: 'center',
  },
  previewWrapper: {
    position: 'relative',
    width: 72,
    height: 72,
    borderRadius: 12,
    overflow: 'hidden',
    flexShrink: 0,
  },
  previewImage: {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
  },
  previewOverlay: {
    position: 'absolute',
    bottom: 0,
    right: 0,
    width: 28,
    height: 28,
    borderRadius: '50%',
    background: '#22C55E',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    border: '2px solid #f0fdf4',
  },
  pdfPreview: {
    width: 72,
    height: 72,
    borderRadius: 12,
    background: '#FFF7ED',
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    flexShrink: 0,
    position: 'relative',
  },
  pdfName: {
    fontSize: 9,
    color: '#9ca3af',
    textAlign: 'center',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
    maxWidth: 60,
  },
  successInfo: {
    flex: 1,
    minWidth: 0,
    display: 'flex',
    flexDirection: 'column',
    gap: 4,
  },
  successBadge: {
    display: 'flex',
    alignItems: 'center',
    gap: 6,
  },
  changeBtn: {
    marginTop: 4,
    fontSize: 12,
    color: '#F97316',
    fontWeight: 600,
    background: 'none',
    border: 'none',
    cursor: 'pointer',
    padding: 0,
    textAlign: 'left',
    textDecoration: 'underline',
  },

  /* Error */
  errorContainer: {
    border: '2px solid #fca5a5',
    borderRadius: 14,
    padding: 14,
    background: '#fef2f2',
    display: 'flex',
    flexDirection: 'column',
    gap: 10,
  },
  errorContent: {
    display: 'flex',
    alignItems: 'center',
    gap: 8,
  },
  errorText: {
    fontSize: 13,
    color: '#dc2626',
    fontWeight: 600,
  },
  retryBtn: {
    padding: '8px 20px',
    borderRadius: 10,
    background: '#fff',
    color: '#dc2626',
    fontWeight: 700,
    fontSize: 13,
    border: '1px solid #fca5a5',
    cursor: 'pointer',
    alignSelf: 'flex-start',
  },

  /* Lightbox */
  lightboxBackdrop: {
    position: 'fixed',
    inset: 0,
    zIndex: 9999,
    background: 'rgba(0,0,0,0.80)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 16,
    cursor: 'zoom-out',
    WebkitTapHighlightColor: 'transparent',
  },
  lightboxContent: {
    position: 'relative',
    maxWidth: '92vw',
    maxHeight: '88vh',
    borderRadius: 16,
    overflow: 'hidden',
    boxShadow: '0 8px 40px rgba(0,0,0,0.4)',
    background: '#000',
  },
  lightboxImage: {
    display: 'block',
    maxWidth: '92vw',
    maxHeight: '88vh',
    objectFit: 'contain',
  },
  lightboxClose: {
    position: 'absolute',
    top: 10,
    right: 10,
    width: 38,
    height: 38,
    borderRadius: '50%',
    background: 'rgba(0,0,0,0.55)',
    border: 'none',
    color: '#fff',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    backdropFilter: 'blur(4px)',
  },
};

export default FileUploadBox;
