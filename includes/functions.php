<?php
declare(strict_types=1);

function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function icon_view(): string {
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
}

function icon_edit(): string {
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
}

function icon_delete(): string {
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>';
}

function icon_chevron(): string {
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
}

function app_base(array $config): string {
    if (!empty($config['base_url'])) return rtrim($config['base_url'], '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $script = preg_replace('#/admin$#', '', $script);
    return rtrim($scheme . '://' . $host . ($script === '/' ? '' : $script), '/');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid or expired form token.');
    }
}

function generate_public_id(PDO $db): string {
    do {
        $id = 'DB-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        $s = $db->prepare('SELECT 1 FROM body_reports WHERE public_id=? LIMIT 1');
        $s->execute([$id]);
    } while ($s->fetchColumn());
    return $id;
}

function status_labels(): array {
    return [
        'new'=>'New Report',
        'verification_required'=>'Verification Required',
        'verified'=>'Verified / Confirmed',
        'confirmed'=>'Confirmed',
        'police_informed'=>'Nepal Police Informed',
        'team_dispatched'=>'Team Dispatched',
        'recovered'=>'Recovered / Rescued',
        'shifted'=>'Shifted to Hospital / Mortuary',
        'buried'=>'Buried / Disposed',
        'identified'=>'Identified',
        'closed'=>'Closed',
        'false_report'=>'False Report',
        'invalid'=>'Invalid',
        'duplicate'=>'Duplicate',
        'unable_to_locate'=>'Unable to Locate',
    ];
}

function terminal_statuses(): array {
    return ['closed','false_report','invalid','duplicate','unable_to_locate'];
}

function is_terminal_status(string $status): bool {
    return in_array($status, terminal_statuses(), true);
}

function body_type_labels(): array {
    return ['human'=>'Human / मानिस', 'animal'=>'Animal / जनावर', 'unsure'=>'Unsure / यकिन छैन'];
}

function weather_condition_labels(): array {
    return ['clear'=>'Clear / सफा','raining'=>'Raining / पानी परिरहेको','extreme_heat'=>'Extreme heat / अत्यधिक गर्मी'];
}
function animal_species_labels(): array {
    return ['cow_ox'=>'Cow/Ox / गाई/गोरु','buffalo'=>'Buffalo / भैँसी/राँगो','goat_sheep'=>'Goat/Sheep / बाख्रा/भेडा','pig_boar'=>'Pig/Boar / सुँगुर/बाँदेल','chicken_duck'=>'Chicken/Duck / कुखुरा/हाँस','other'=>'Other / अन्य'];
}
function estimated_size_labels(): array {
    return ['large'=>'Large / ठूलो (उदा: भैँसी/गाई)','medium'=>'Medium / मध्यम (उदा: बाख्रा)','small'=>'Small / सानो'];
}
function decomposition_state_labels(): array {
    return ['fresh'=>'Fresh / सामान्य/ताजा शव','decomposing'=>'Decomposing / सड्न सुरु भएको','fully_decomposed'=>'Fully decomposed / पूर्ण रूपमा सडेको','mutilated'=>'Mutilated / अंगभंग भएको'];
}
function distance_water_source_labels(): array {
    return ['under_50m'=>'Under 50m / ५० मिटर भन्दा कम','50_to_200m'=>'50–200m / ५० देखि २०० मिटर सम्म','over_200m'=>'Over 200m / २०० मिटर भन्दा बढी'];
}
function distance_settlement_labels(): array {
    return ['under_50m'=>'Under 50m / ५० मिटर भन्दा कम','100_to_500m'=>'100–500m / १०० देखि ५०० मिटर','over_500m'=>'Over 500m / ५०० मिटर भन्दा बढी'];
}
function disposal_method_labels(): array {
    return ['trench_burial'=>'Trench burial / खाडल खनेर पुर्ने','offsite_transport'=>'Off-site transport with bio-bags / बायो-सेक प्रयोग गरी अन्यत्र सुरक्षित स्थानान्तरण','other_approved'=>'Other approved method / अन्य स्वीकृत विधि (कम्पोस्टिङ वा दाउरा प्रयोग गरी जलाउने)'];
}
function equipment_needed_labels(): array {
    return ['excavator_dozer'=>'Excavator/Dozer / डोजर/जेसीबी','tractor_truck'=>'Tractor/Truck / ट्र्याक्टर/ट्रक','carcass_bag'=>'Carcass carrying bag/plastic / शव बोक्ने झोला/प्लास्टिक','spade_hoe'=>'Spade/hoe / खन्ती/कोदालो','rope'=>'Rope / डोरी'];
}
function disinfection_materials_labels(): array {
    return ['lime'=>'Lime / चुन','bleaching_powder'=>'Bleaching powder / ब्लिचिङ पाउडर','formalin'=>'Formalin / फर्मालिन','detergent_soap'=>'Detergent/soap / डिटर्जेन्ट/साबुन'];
}

function workflow_next_action(array $r): array {
    if (is_terminal_status((string)($r['status'] ?? ''))) {
        return ['key'=>'closed','label'=>'Case Closed','description'=>'This case is already closed or terminal.'];
    }
    $type=(string)($r['body_type'] ?? 'unsure');
    if ($type==='unsure') {
        if (empty($r['confirmed_at'])) {
            return ['key'=>'confirm','label'=>'Confirm Unsure Report','description'=>'Verify that a genuine report/location exists, even though the body type is not yet known.'];
        }
        if (empty($r['team_dispatched_at'])) {
            return ['key'=>'dispatch_team','label'=>'Send Assessment / Rescue Team','description'=>'Dispatch the nearest responder to inspect the location and determine whether the body is Human or Animal.'];
        }
        return ['key'=>'classify_found','label'=>'Record What the Team Found','description'=>'Choose Human or Animal. Human immediately continues to Nepal Police notification; Animal can continue with a local volunteer or animal response team.'];
    }
    if (empty($r['confirmed_at'])) {
        return ['key'=>'confirm','label'=>'Confirm Case','description'=>'Verify the report and confirm that the body/location is genuine.'];
    }
    if ($type==='human') {
        if (empty($r['police_informed'])) return ['key'=>'inform_police','label'=>'Inform Nepal Police','description'=>'Record that Nepal Police has been informed, then share the exact location securely.'];
        if (empty($r['team_dispatched_at'])) return ['key'=>'dispatch_team','label'=>'Dispatch Response Team','description'=>'Record the police/rescue/recovery team sent to the exact location.'];
        if (empty($r['recovered_at'])) return ['key'=>'recover','label'=>'Mark Recovered','description'=>'Record that the human remains have been recovered from the reported location.'];
        if (empty($r['destination_name'])) return ['key'=>'handover','label'=>'Record Handover / Mortuary','description'=>'Record transfer to Nepal Police, hospital or mortuary before closing.'];
        return ['key'=>'close','label'=>'Close Case','description'=>'Close after recovery and official handover/transfer are complete.'];
    }
    if ($type==='animal') {
        if (empty($r['team_dispatched_at'])) return ['key'=>'dispatch_team','label'=>'Send Rescue / Recovery Team','description'=>'Assign and dispatch the team to the reported animal body.'];
        if (empty($r['recovered_at'])) return ['key'=>'recover','label'=>'Mark Recovered','description'=>'Record that the animal body has been recovered.'];
        if (empty($r['buried_at'])) return ['key'=>'bury','label'=>'Mark Buried / Disposed','description'=>'Record burial or safe final disposal details.'];
        return ['key'=>'close','label'=>'Close Case','description'=>'Close the animal case after burial/final disposal is recorded.'];
    }
    return ['key'=>'confirm','label'=>'Confirm Case','description'=>'Verify the report before continuing.'];
}

function workflow_next_label(array $r): string {
    return workflow_next_action($r)['label'];
}

function is_admin_logged_in(): bool {
    return !empty($_SESSION['admin_user']['id']);
}

function require_admin(): void {
    global $config;
    if (!is_admin_logged_in()) {
        header('Location: ' . app_base($config) . '/admin/login');
        exit;
    }
}

function admin_user(): array {
    return $_SESSION['admin_user'] ?? [];
}

function can_edit(): bool {
    return in_array(admin_user()['role'] ?? '', ['admin','operator'], true);
}

function can_close(): bool {
    return (admin_user()['role'] ?? '') === 'admin' || !empty(admin_user()['can_close']);
}

function require_operator(): void {
    require_admin();
    if (!can_edit()) {
        http_response_code(403);
        exit('Operator or Admin permission required to file reports.');
    }
}

function ip_hash(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', $ip);
}

function audit_log(PDO $db, string $action, ?string $entityType=null, ?string $entityId=null, ?string $details=null): void {
    $stmt=$db->prepare('INSERT INTO audit_logs(admin_id,action,entity_type,entity_id,details,ip_hash) VALUES(?,?,?,?,?,?)');
    $stmt->execute([admin_user()['id'] ?? null,$action,$entityType,$entityId,$details,ip_hash()]);
}

function report_photos(PDO $db, int $reportId): array {
    $s=$db->prepare('SELECT * FROM report_photos WHERE report_id=? ORDER BY id');
    $s->execute([$reportId]);
    return $s->fetchAll();
}

function upload_photos(PDO $db, int $reportId, array $config): void {
    if (empty($_FILES['photos']) || !isset($_FILES['photos']['name']) || !is_array($_FILES['photos']['name'])) return;
    $maxPhotos=(int)($config['security']['max_photos'] ?? 5);
    $maxBytes=(int)($config['security']['max_upload_mb'] ?? 8)*1024*1024;
    $count=min(count($_FILES['photos']['name']),$maxPhotos);
    $dir=dirname(__DIR__).'/uploads';
    if (!is_dir($dir)) mkdir($dir,0755,true);
    $finfo=new finfo(FILEINFO_MIME_TYPE);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    for ($i=0;$i<$count;$i++) {
        if ($_FILES['photos']['error'][$i]===UPLOAD_ERR_NO_FILE) continue;
        if ($_FILES['photos']['error'][$i]!==UPLOAD_ERR_OK) throw new RuntimeException('Photo upload failed.');
        $tmp=$_FILES['photos']['tmp_name'][$i];
        $size=(int)$_FILES['photos']['size'][$i];
        if ($size<1 || $size>$maxBytes) throw new RuntimeException('Each photo must be within the configured size limit.');
        $mime=$finfo->file($tmp);
        if (!isset($allowed[$mime])) throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
        if (@getimagesize($tmp)===false) throw new RuntimeException('Invalid image file.');
        $sha=hash_file('sha256',$tmp);
        $stored=$reportId.'-'.bin2hex(random_bytes(12)).'.'.$allowed[$mime];
        $dest=$dir.'/'.$stored;
        if (!move_uploaded_file($tmp,$dest)) throw new RuntimeException('Could not store photo.');
        @chmod($dest,0644);
        $q=$db->prepare('INSERT INTO report_photos(report_id,stored_name,original_name,mime_type,file_size,sha256) VALUES(?,?,?,?,?,?)');
        $q->execute([$reportId,$stored,basename((string)$_FILES['photos']['name'][$i]),$mime,$size,$sha]);
    }
}

function public_coord(float $v, array $config): float {
    return round($v, (int)($config['security']['public_coordinate_decimals'] ?? 3));
}

function maps_url(float $lat, float $lng): string {
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(number_format($lat, 7, '.', '') . ',' . number_format($lng, 7, '.', ''));
}

function create_case_share(PDO $db, int $reportId, string $recipientType, string $recipientName, string $recipientContact, string $note, int $hours=24): string {
    if (!in_array($recipientType, ['police','rescue_team','other'], true)) $recipientType='other';
    $hours=max(1,min($hours,168));
    $token=rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $hash=hash('sha256',$token);
    $expires=(new DateTimeImmutable('now'))->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s');
    $q=$db->prepare('INSERT INTO case_share_links(report_id,token_hash,recipient_type,recipient_name,recipient_contact,note,expires_at,created_by) VALUES(?,?,?,?,?,?,?,?)');
    $q->execute([$reportId,$hash,$recipientType,mb_substr($recipientName,0,180),mb_substr($recipientContact,0,80),mb_substr($note,0,500),$expires,admin_user()['id'] ?? null]);
    return $token;
}

function json_response($data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

function validate_phone(string $v): bool {
    return (bool)preg_match('/^[0-9+\-\s()]{7,25}$/', $v);
}
