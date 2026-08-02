<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

$uploadDir = 'uploads/sliders/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// Handle Add
if (isset($_POST['add_slider'])) {
    $media_type = mysqli_real_escape_string($conn, $_POST['media_type']);
    $overlay = (int)$_POST['overlay_opacity'];
    $order = (int)$_POST['display_order'];
    $heading = mysqli_real_escape_string($conn, $_POST['heading'] ?? '');
    $subheading = mysqli_real_escape_string($conn, $_POST['subheading'] ?? '');
    $btn_text = mysqli_real_escape_string($conn, $_POST['button_text'] ?? '');
    $btn_link = mysqli_real_escape_string($conn, $_POST['button_link'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $media_file = '';

    if (!empty($_FILES['media_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
        $allowed_img = ['jpg','jpeg','png','webp','gif'];
        $allowed_vid = ['mp4','webm','ogg'];
        $allowed = ($media_type == 'video') ? $allowed_vid : $allowed_img;
        if (in_array($ext, $allowed)) {
            $filename = 'slider_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['media_file']['tmp_name'], $uploadDir . $filename);
            $media_file = $uploadDir . $filename;
        } else {
            $_SESSION['msg'] = "Invalid file format!";
            $_SESSION['msg_type'] = "danger";
            header("Location: sliders.php");
            exit;
        }
    }

    $sql = "INSERT INTO sliders (media_type, media_file, overlay_opacity, display_order, heading, subheading, button_text, button_link, is_active)
            VALUES ('$media_type', '$media_file', $overlay, $order, '$heading', '$subheading', '$btn_text', '$btn_link', $is_active)";
    if ($conn->query($sql)) {
        $_SESSION['msg'] = "Slider added successfully!";
    } else {
        $_SESSION['msg'] = "Error: " . $conn->error;
        $_SESSION['msg_type'] = "danger";
    }
    header("Location: sliders.php");
    exit;
}

// Handle Update
if (isset($_POST['update_slider'])) {
    $id = (int)$_POST['slider_id'];
    $media_type = mysqli_real_escape_string($conn, $_POST['media_type']);
    $overlay = (int)$_POST['overlay_opacity'];
    $order = (int)$_POST['display_order'];
    $heading = mysqli_real_escape_string($conn, $_POST['heading'] ?? '');
    $subheading = mysqli_real_escape_string($conn, $_POST['subheading'] ?? '');
    $btn_text = mysqli_real_escape_string($conn, $_POST['button_text'] ?? '');
    $btn_link = mysqli_real_escape_string($conn, $_POST['button_link'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $media_sql = "";
    if (!empty($_FILES['media_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
        $allowed_img = ['jpg','jpeg','png','webp','gif'];
        $allowed_vid = ['mp4','webm','ogg'];
        $allowed = ($media_type == 'video') ? $allowed_vid : $allowed_img;
        if (in_array($ext, $allowed)) {
            $old = $conn->query("SELECT media_file FROM sliders WHERE id=$id")->fetch_assoc();
            if ($old && !empty($old['media_file']) && file_exists($old['media_file'])) unlink($old['media_file']);
            $filename = 'slider_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['media_file']['tmp_name'], $uploadDir . $filename);
            $media_sql = ", media_file='" . $uploadDir . $filename . "'";
        }
    }

    $sql = "UPDATE sliders SET media_type='$media_type', overlay_opacity=$overlay, display_order=$order,
            heading='$heading', subheading='$subheading', button_text='$btn_text', button_link='$btn_link',
            is_active=$is_active $media_sql WHERE id=$id";
    if ($conn->query($sql)) {
        $_SESSION['msg'] = "Slider updated successfully!";
    } else {
        $_SESSION['msg'] = "Error: " . $conn->error;
        $_SESSION['msg_type'] = "danger";
    }
    header("Location: sliders.php");
    exit;
}

// Load slider for editing
$editSlider = null;
$isEdit = false;
if (isset($_GET['edit_id'])) {
    $eid = (int)$_GET['edit_id'];
    $editSlider = $conn->query("SELECT * FROM sliders WHERE id=$eid")->fetch_assoc();
    if ($editSlider) $isEdit = true;
}

$nextOrder = 1;
if (!$isEdit) {
    $r = $conn->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM sliders");
    if ($r) $nextOrder = (int)$r->fetch_assoc()['next_order'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $isEdit ? 'Edit Slider' : 'Add New Slider' ?></title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>

    <style>
        .slider-form-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .slider-form-header {
            background: linear-gradient(135deg, #6a11cb 0%, #7b5ea7 50%, #a855f7 100%);
            color: #fff;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
        }
        .slider-form-header i { margin-right: 8px; }
        .slider-form-body { padding: 24px 28px; }

        .form-label-custom {
            font-weight: 600;
            font-size: 13px;
            color: #333;
            margin-bottom: 6px;
        }
        .form-label-custom .req { color: red; }

        .opacity-slider-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .opacity-slider-wrap input[type=range] {
            flex: 1;
            accent-color: #6a11cb;
        }
        .opacity-val {
            background: #6a11cb;
            color: #fff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            min-width: 42px;
            text-align: center;
        }

        .toggle-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; cursor: pointer; inset: 0;
            background-color: #ccc; border-radius: 26px; transition: 0.3s;
        }
        .toggle-slider:before {
            content: ""; position: absolute; height: 20px; width: 20px;
            left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s;
        }
        .toggle-switch input:checked + .toggle-slider { background-color: #6a11cb; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(24px); }

        .media-preview {
            border: 2px dashed #c0a8f0;
            border-radius: 10px;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f8f9fa;
            margin-bottom: 8px;
            cursor: pointer;
            position: relative;
            transition: border-color 0.25s, background 0.25s;
            user-select: none;
        }
        .media-preview:hover {
            border-color: #6a11cb;
            background: #f1ebff;
        }
        .media-preview:hover .upload-hover-hint { opacity: 1; }
        .media-preview video, .media-preview img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            pointer-events: none;
        }
        .media-preview .placeholder-text {
            color: #aaa;
            font-size: 14px;
            text-align: center;
            pointer-events: none;
        }
        /* Hover overlay shown when a file is already previewed */
        .upload-hover-hint {
            position: absolute;
            inset: 0;
            background: rgba(106,17,203,0.55);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            gap: 6px;
            opacity: 0;
            transition: opacity 0.25s;
            border-radius: 8px;
            pointer-events: none;
        }
        .upload-hover-hint i { font-size: 28px; }

        .tip-box {
            background: #f8f9fa;
            border-left: 3px solid #6a11cb;
            padding: 8px 12px;
            font-size: 12px;
            color: #666;
            border-radius: 0 6px 6px 0;
            margin-top: 6px;
        }
        .tip-box code {
            background: #eee;
            padding: 1px 5px;
            border-radius: 3px;
            color: #c7254e;
            font-size: 11px;
        }

        /* ── Gradient row ── */
        .grad-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }
        .grad-row label {
            font-size: 12px;
            color: #555;
            font-weight: 600;
            white-space: nowrap;
            margin: 0;
        }
        .grad-row input[type="color"] {
            width: 38px;
            height: 34px;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 2px;
            cursor: pointer;
            background: none;
        }
        .grad-row .gt-btn-apply {
            background: linear-gradient(135deg, #6a11cb, #a855f7);
            color: #fff; border: none;
            padding: 6px 16px; border-radius: 6px;
            font-size: 13px; font-weight: 600; cursor: pointer;
        }
        .grad-row .gt-btn-apply:hover { opacity: .88; }
        .grad-row .gt-btn-clear {
            background: #fff; color: #888;
            border: 1px solid #ddd;
            padding: 6px 12px; border-radius: 6px;
            font-size: 13px; cursor: pointer;
        }
        .grad-row .gt-btn-clear:hover { background: #fee2e2; color: #b91c1c; }

        /* ── Gradient live preview ── */
        .grad-preview-box {
            display: none;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
            padding: 10px 14px;
            background: #1a1a2e;
            border-radius: 8px;
            border: 1px solid #2e2b55;
        }
        .grad-preview-box .gp-label {
            font-size: 11px;
            color: #94a3b8;
            white-space: nowrap;
            font-weight: 500;
        }
        .grad-preview-box .gp-text {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: .5px;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: background .15s;
        }

        .btn-save-slider {
            background: linear-gradient(135deg, #6a11cb, #a855f7);
            color: #fff;
            border: none;
            padding: 9px 24px;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-save-slider:hover { opacity: 0.9; color: #fff; }

        .btn-cancel-slider {
            background: #333;
            color: #fff;
            border: none;
            padding: 9px 24px;
            border-radius: 6px;
            font-weight: 600;
        }
        .btn-cancel-slider:hover { background: #222; color: #fff; }

        .back-link {
            color: #6a11cb;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
        }
        .back-link:hover { color: #5a0fb0; text-decoration: none; }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <?php include __DIR__ . '/includes/top-header.php'; ?>
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <?php include __DIR__ . '/includes/page-header.php'; ?>

            <section class="content">
                <div class="container-fluid">

                    <!-- Back Link -->
                    <a href="sliders.php" class="back-link mb-3 d-inline-block">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Sliders
                    </a>

                    <!-- SLIDER FORM -->
                    <div class="card slider-form-card">
                        <div class="slider-form-header">
                            <i class="fas fa-info-circle"></i> Slider Information
                        </div>
                        <div class="slider-form-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <?php if ($isEdit): ?>
                                    <input type="hidden" name="slider_id" value="<?= $editSlider['id'] ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <!-- LEFT COLUMN -->
                                    <div class="col-md-8">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label-custom">Media Type (<span style="font-size:12px; color:#666;">1265*388 px</span>)<span class="req">*</span></label>
                                                    <select name="media_type" id="mediaType" class="form-control">
                                                        <option value="video" <?= ($isEdit && $editSlider['media_type']=='video') ? 'selected' : '' ?>>Video</option>
                                                        <option value="image" <?= ($isEdit && $editSlider['media_type']=='image') ? 'selected' : '' ?>>Image</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <label class="form-label-custom mt-2" id="mediaLabel">
                                            <?= ($isEdit && $editSlider['media_type']=='image') ? 'Slider Image' : 'Slider Video' ?>
                                        </label>

                                        <!-- Hidden file input -->
                                        <input type="file" name="media_file" id="mediaFileInput" style="display:none;"
                                               accept="video/mp4,video/webm,video/ogg,image/jpeg,image/png,image/webp,image/gif">

                                        <!-- Clickable Media Preview -->
                                        <div class="media-preview" id="mediaPreview" onclick="document.getElementById('mediaFileInput').click()" title="Click to select file">
                                            <?php if ($isEdit && !empty($editSlider['media_file'])): ?>
                                                <?php if ($editSlider['media_type'] == 'video'): ?>
                                                    <video controls muted style="width:100%"><source src="<?= $editSlider['media_file'] ?>" type="video/mp4"></video>
                                                <?php else: ?>
                                                    <img src="<?= $editSlider['media_file'] ?>" alt="Slider">
                                                <?php endif; ?>
                                                <div class="upload-hover-hint">
                                                    <i class="fas fa-cloud-upload-alt"></i> Click to replace
                                                </div>
                                            <?php else: ?>
                                                <span class="placeholder-text">
                                                    <i class="fas fa-cloud-upload-alt fa-2x d-block mb-2"></i>
                                                    Click to select file
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted" id="fileHelpText">
                                            <?= $isEdit ? 'Click the box above to replace the current file.' : 'Click the box above to choose a file.' ?>
                                            Allowed formats: MP4, WebM, OGG. Recommended size: 1920x800px
                                        </small>
                                    </div>

                                    <!-- RIGHT COLUMN -->
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label-custom">Overlay Opacity (%)</label>
                                            <div class="opacity-slider-wrap">
                                                <input type="range" name="overlay_opacity" id="opacityRange" min="0" max="100"
                                                       value="<?= $isEdit ? $editSlider['overlay_opacity'] : 40 ?>">
                                                <span class="opacity-val" id="opacityVal"><?= $isEdit ? $editSlider['overlay_opacity'] : 40 ?>%</span>
                                            </div>
                                        </div>

                                        <div class="form-group mt-3">
                                            <label class="form-label-custom">Display Order</label>
                                            <input type="number" name="display_order" class="form-control" min="1"
                                                   value="<?= $isEdit ? $editSlider['display_order'] : $nextOrder ?>">
                                        </div>

                                        <div class="form-group mt-3">
                                            <label class="form-label-custom d-block">Active</label>
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="is_active"
                                                       <?= (!$isEdit || $editSlider['is_active']) ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <!-- Heading -->
                                <div class="form-group">
                                    <label class="form-label-custom">Heading</label>
                                    <input type="text" name="heading" id="headingInput" class="form-control"
                                           value="<?= $isEdit ? htmlspecialchars($editSlider['heading']) : '' ?>"
                                           placeholder="Your Destination Treat">

                                    <!-- Gradient color row -->
                                    <div class="grad-row">
                                        <label><i class="fas fa-paint-brush mr-1" style="color:#7c3aed;"></i> Gradient:</label>
                                        <input type="color" id="gradColor1" value="#f59e0b" title="Color 1">
                                        <input type="color" id="gradColor2" value="#ef4444" title="Color 2">
                                        <select id="gradDirection" title="Direction" style="height:34px; border:1px solid #ccc; border-radius:6px; padding:0 8px; font-size:13px; cursor:pointer; background:#fff; color:#333;">
                                            <option value="135deg">↗ Diagonal</option>
                                            <option value="90deg">→ Horizontal</option>
                                            <option value="180deg">↓ Vertical</option>
                                            <option value="45deg">↙ 45°</option>
                                            <option value="270deg">← Reverse</option>
                                        </select>
                                        <button type="button" class="gt-btn-apply" id="btnApplyGradient">Apply to selection</button>
                                        <button type="button" class="gt-btn-clear" id="btnClearSpans" title="Remove all gradient spans"><i class="fas fa-eraser"></i> Clear</button>
                                    </div>

                                    <!-- Live preview of selected text with gradient -->
                                    <div class="grad-preview-box" id="gradPreviewBox">
                                        <span class="gp-label">Preview:</span>
                                        <span class="gp-text" id="gradPreviewText"></span>
                                    </div>

                                    <small class="text-muted">Select a word in the heading, pick two colors — preview appears instantly. Click <strong>Apply to selection</strong> to save.</small>
                                </div>

                                <!-- Subheading -->
                                <div class="form-group mt-3">
                                    <label class="form-label-custom">Subheading</label>
                                    <input type="text" name="subheading" class="form-control"
                                           value="<?= $isEdit ? htmlspecialchars($editSlider['subheading']) : '' ?>"
                                           placeholder='<strong> search</strong> your favorite Vacation Place'>
                                    <div class="tip-box">
                                        <i class="fas fa-info-circle text-primary"></i> You can also use formatting: <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;span&gt;</code>
                                    </div>
                                </div>

                                <!-- Button Text -->
                                <!-- <div class="form-group mt-3">
                                    <label class="form-label-custom">Button Text</label>
                                    <input type="text" name="button_text" class="form-control"
                                           value="<?= $isEdit ? htmlspecialchars($editSlider['button_text']) : '' ?>">
                                </div> -->

                                <!-- Button Link -->
                                <!-- <div class="form-group mt-3">
                                    <label class="form-label-custom">Button Link</label>
                                    <input type="text" name="button_link" class="form-control" placeholder="#"
                                           value="<?= $isEdit ? htmlspecialchars($editSlider['button_link']) : '' ?>">
                                </div> -->

                                <!-- Actions -->
                                <div class="d-flex justify-content-end mt-4" style="gap:10px;">
                                    <a href="sliders.php" class="btn btn-cancel-slider"><i class="fas fa-times mr-1"></i> Cancel</a>
                                    <?php if ($isEdit): ?>
                                        <button type="submit" name="update_slider" class="btn btn-save-slider"><i class="fas fa-save mr-1"></i> Update Slider</button>
                                    <?php else: ?>
                                        <button type="submit" name="add_slider" class="btn btn-save-slider"><i class="fas fa-plus mr-1"></i> Add Slider</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </section>
        </div>

        <?php include __DIR__ . '/includes/footer-links.php'; ?>

        <script>
            $(function() {
                // Opacity slider live update
                $('#opacityRange').on('input', function() {
                    $('#opacityVal').text(this.value + '%');
                });

                // Media type toggle
                $('#mediaType').on('change', function() {
                    var type = $(this).val();
                    if (type === 'video') {
                        $('#mediaLabel').text('Slider Video');
                        $('#mediaFileInput').attr('accept', 'video/mp4,video/webm,video/ogg');
                        $('#fileHelpText').html('<?= $isEdit ? "Click the box above to replace the current file. " : "Click the box above to choose a file. " ?>Allowed formats: MP4, WebM, OGG. Recommended size: 1920x800px');
                    } else {
                        $('#mediaLabel').text('Slider Image');
                        $('#mediaFileInput').attr('accept', 'image/jpeg,image/png,image/webp,image/gif');
                        $('#fileHelpText').html('<?= $isEdit ? "Click the box above to replace the current file. " : "Click the box above to choose a file. " ?>Allowed formats: JPG, PNG, WebP, GIF. Recommended size: 1920x800px');
                    }
                });

                // ── Gradient ──
                var savedSel = { start: 0, end: 0 };
                var headingInput = document.getElementById('headingInput');

                function updateGradPreview() {
                    var s = savedSel.start, e = savedSel.end;
                    var word = headingInput.value.substring(s, e).trim();
                    if (!word) { $('#gradPreviewBox').hide(); return; }
                    var c1   = $('#gradColor1').val();
                    var c2   = $('#gradColor2').val();
                    var dir  = $('#gradDirection').val();
                    var grad = 'linear-gradient(' + dir + ',' + c1 + ',' + c2 + ')';
                    $('#gradPreviewText').text(word).css({
                        background: grad,
                        '-webkit-background-clip': 'text',
                        '-webkit-text-fill-color': 'transparent',
                        'background-clip': 'text'
                    });
                    $('#gradPreviewBox').css('display', 'flex');
                }

                // Save selection + refresh preview whenever selection changes or input blurs
                $(headingInput).on('mouseup keyup select blur', function() {
                    savedSel.start = headingInput.selectionStart;
                    savedSel.end   = headingInput.selectionEnd;
                    updateGradPreview();
                });

                // Refresh preview when colors or direction change
                $('#gradColor1, #gradColor2, #gradDirection').on('input change', updateGradPreview);

                $('#btnApplyGradient').on('click', function() {
                    var s = savedSel.start, e = savedSel.end;
                    if (s === e) {
                        $(headingInput).css('outline', '2px solid #a855f7');
                        setTimeout(function(){ $(headingInput).css('outline', ''); }, 700);
                        headingInput.focus();
                        return;
                    }
                    var c1  = $('#gradColor1').val();
                    var c2  = $('#gradColor2').val();
                    var dir = $('#gradDirection').val();
                    var style = 'background:linear-gradient(' + dir + ',' + c1 + ',' + c2 + ');-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;';
                    var selected = headingInput.value.substring(s, e);
                    var wrapped  = '<span style="' + style + '">' + selected + '</span>';
                    headingInput.value = headingInput.value.substring(0, s) + wrapped + headingInput.value.substring(e);
                    savedSel.start = savedSel.end = s + wrapped.length;
                    headingInput.setSelectionRange(savedSel.start, savedSel.end);
                    headingInput.focus();
                    $('#gradPreviewBox').hide();
                });

                $('#btnClearSpans').on('click', function() {
                    headingInput.value = headingInput.value.replace(/<span[^>]*>(.*?)<\/span>/gi, '$1');
                    $('#gradPreviewBox').hide();
                    headingInput.focus();
                });

                // Live file preview
                $('#mediaFileInput').on('change', function() {
                    var file = this.files[0];
                    if (!file) return;
                    var preview = $('#mediaPreview');
                    var url = URL.createObjectURL(file);
                    var hoverHint = '<div class="upload-hover-hint"><i class="fas fa-cloud-upload-alt"></i> Click to replace</div>';
                    if (file.type.startsWith('video/')) {
                        preview.html('<video controls muted style="width:100%"><source src="' + url + '" type="' + file.type + '"></video>' + hoverHint);
                    } else {
                        preview.html('<img src="' + url + '" alt="Preview">' + hoverHint);
                    }
                    // prevent video element clicks from bubbling to parent (which would re-open picker)
                    preview.find('video').on('click', function(e){ e.stopPropagation(); });
                });
            });
        </script>

    </div>
</body>
</html>
