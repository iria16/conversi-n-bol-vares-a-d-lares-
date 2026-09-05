<?php
// Debug temporal — ELIMINAR después de verificar
header('Content-Type: application/json');
echo json_encode([
    'files'              => $_FILES,
    'upload_max'         => ini_get('upload_max_filesize'),
    'post_max'           => ini_get('post_max_size'),
    'file_uploads'       => ini_get('file_uploads'),
    'tmp_dir'            => sys_get_temp_dir(),
    'tmp_writable'       => is_writable(sys_get_temp_dir()),
    'upload_dir'         => __DIR__,
    'upload_dir_writable'=> is_writable(__DIR__),
]);
