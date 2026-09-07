<?php
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    redirect(role_home(current_user()));
}

$errors = [];

$provinces    = ['Koshi', 'Madhesh', 'Bagmati', 'Gandaki', 'Lumbini', 'Karnali', 'Sudur Paschim'];
$countries    = ['Nepal', 'India', 'United States', 'Other'];
$deptCodeToId = [];
foreach (db()->query('SELECT id, code FROM departments') as $d) {
    $deptCodeToId[$d['code']] = (int)$d['id'];
}

// Re-populate helper
function old(string $key, string $default = ''): string
{
    return htmlspecialchars(trim((string)($_POST[$key] ?? $default)), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $post = fn(string $key): string => trim((string)($_POST[$key] ?? ''));

    $role        = ($_POST['userRole'] ?? '') === 'authority' ? 'authority' : 'citizen';
    $country     = $post('country') ?: 'Nepal';
    $firstName   = $post('first_name');
    $middleName  = $post('middle_name');
    $lastName    = $post('last_name');
    $email       = strtolower($post('email'));
    $phone       = $post('phone');
    $password    = (string)($_POST['password'] ?? '');
    $confirm     = (string)($_POST['password_confirm'] ?? '');
    $identityNo  = $post('identity_no');
    $province    = $post('province');
    $district    = $post('district');
    $municipality= $post('municipality');
    $tole        = $post('tole');
    $wardNo      = $post('ward_no');
    $authDept    = $post('auth_department');
    $officerId   = $post('auth_officer_id');
    $designation = $post('auth_designation');
    $officialEmail = strtolower($post('auth_official_email'));
    $deptAddress = $post('auth_address');

    /* ---- Personal details ---- */
    if ($firstName === '')                       $errors[] = 'First name is required.';
    if ($lastName === '')                        $errors[] = 'Last name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

    if ($phone !== '' && $country === 'Nepal'
        && !preg_match('/^(\+977)?(98|97|96)\d{8}$/', preg_replace('/\s+/', '', $phone))) {
        $errors[] = 'Enter a valid 10-digit mobile number starting with 98/97/96.';
    }

    if (strlen($password) < 8)                   $errors[] = 'Password must be at least 8 characters long.';
    if ($password !== $confirm)                  $errors[] = 'Passwords do not match.';

    /* ---- Identity & citizenship ---- */
    if (!in_array($country, $countries, true))   $errors[] = 'Invalid country selected.';
    if ($identityNo === '') {
        $errors[] = ($country === 'Nepal' ? 'Citizenship No.' : 'Passport No.') . ' is required.';
    } elseif ($country === 'Nepal' && !preg_match('#^[0-9/-]{5,20}$#', $identityNo)) {
        $errors[] = 'Enter a valid Citizenship Number.';
    } elseif ($country !== 'Nepal' && !preg_match('/^[A-Z0-9]{6,12}$/i', $identityNo)) {
        $errors[] = 'Enter a valid Passport Number.';
    }

    /* ---- Residential address ---- */
    if (!in_array($province, $provinces, true))  $errors[] = 'Please select a valid province.';
    if ($district === '')                        $errors[] = 'District is required.';
    if ($municipality === '')                    $errors[] = 'Municipality / Rural Municipality is required.';
    if ($tole === '')                            $errors[] = 'Tole name is required.';
    if (!ctype_digit($wardNo) || (int)$wardNo < 1 || (int)$wardNo > 35) {
        $errors[] = 'Ward No. must be between 1 and 35.';
    }

    /* ---- Authority-only fields ---- */
    $departmentId = null;
    if ($role === 'authority') {
        if (!isset($deptCodeToId[$authDept]))    $errors[] = 'Please select your department / agency.';
        else                                     $departmentId = $deptCodeToId[$authDept];
        if ($officerId === '')                   $errors[] = 'Department / Officer ID is required.';
        if ($designation === '')                 $errors[] = 'Official designation / rank is required.';
        if (!filter_var($officialEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid official department email is required.';
        if ($deptAddress === '')                 $errors[] = 'Department address is required.';
    }

    /* ---- Duplicate email ---- */
    if (!$errors) {
        $dup = db()->prepare('SELECT id FROM users WHERE email = ?');
        $dup->execute([$email]);
        if ($dup->fetch()) $errors[] = 'An account with this email already exists.';
    }

    /* ---- Persist ---- */
    if (!$errors) {
        $fullName = trim($firstName . ($middleName !== '' ? ' ' . $middleName : '') . ' ' . $lastName);

        db()->prepare(
            'INSERT INTO users
                (name, first_name, middle_name, last_name, email, phone,
                 country, identity_type, identity_no,
                 province, district, municipality, tole, ward_no,
                 officer_id, designation, official_email, dept_address,
                 password, role, department_id, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $fullName, $firstName ?: null, $middleName ?: null, $lastName ?: null,
            $email, $phone !== '' ? $phone : null,
            $country, $country === 'Nepal' ? 'citizenship' : 'passport', $identityNo,
            $province, $district, $municipality, $tole, (int)$wardNo,
            $role === 'authority' ? $officerId : null,
            $role === 'authority' ? $designation : null,
            $role === 'authority' ? $officialEmail : null,
            $role === 'authority' ? $deptAddress : null,
            password_hash($password, PASSWORD_DEFAULT),
            $role, $departmentId,
            $role === 'citizen' ? 1 : 0, // authority accounts await admin approval
        ]);

        if ($role === 'authority') {
            flash('info', '<strong>Application received!</strong> Your authority account is pending administrator approval. You will be able to log in once it is verified.');
        } else {
            flash('success', 'Account created successfully. You can now log in.');
        }
        redirect('auth/login.php');
    }
}

$page_title = 'Register';
$extra_head = '<link rel="stylesheet" href="' . asset('assets/css/register.css') . '">';
require __DIR__ . '/../includes/header.php';

$selRole = (($_POST['userRole'] ?? '') === 'authority') ? 'authority' : 'citizen';
?>

<div class="registration-container">
  <div class="form-header">
    <h2>SCERRS Registration</h2>
    <p>Create an account to submit emergency reports or manage dispatch operations.</p>
  </div>

  <?php if ($errors): ?>
    <div class="server-errors">
      <strong>Please fix the following:</strong>
      <ul>
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form id="scerrsRegisterForm" method="post" action="<?= url('auth/register.php') ?>">
    <?= csrf_field() ?>

    <!-- User Role Selector -->
    <div class="role-selection">
      <label class="role-option">
        <input type="radio" name="userRole" value="citizen" <?= $selRole === 'citizen' ? 'checked' : '' ?> onchange="toggleRoleFields()">
        <span>Citizen User</span>
      </label>
      <label class="role-option">
        <input type="radio" name="userRole" value="authority" <?= $selRole === 'authority' ? 'checked' : '' ?> onchange="toggleRoleFields()">
        <span>Authority / Responder</span>
      </label>
    </div>

    <!-- Personal Information -->
    <div class="form-section">
      <h3 class="section-title">Personal Details</h3>
      <div class="form-grid grid-3">
        <div class="form-group">
          <label>First Name *</label>
          <input type="text" name="first_name" placeholder="First Name" value="<?= old('first_name') ?>" required>
        </div>
        <div class="form-group">
          <label>Middle Name</label>
          <input type="text" name="middle_name" placeholder="Middle Name" value="<?= old('middle_name') ?>">
        </div>
        <div class="form-group">
          <label>Last Name *</label>
          <input type="text" name="last_name" placeholder="Last Name" value="<?= old('last_name') ?>" required>
        </div>
      </div>

      <div class="form-grid grid-2">
        <div class="form-group">
          <label>Email Address *</label>
          <input type="email" name="email" placeholder="example@domain.com" value="<?= old('email') ?>" required>
        </div>
        <div class="form-group">
          <label>Phone / Mobile No. *</label>
          <input type="tel" name="phone" placeholder="+977 98XXXXXXXX" value="<?= old('phone') ?>" required>
        </div>
      </div>

      <div class="form-grid grid-2">
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" placeholder="Min. 8 characters" minlength="8" required>
        </div>
        <div class="form-group">
          <label>Confirm Password *</label>
          <input type="password" name="password_confirm" placeholder="Re-enter password" minlength="8" required>
        </div>
      </div>
    </div>

    <!-- Identity Verification -->
    <div class="form-section">
      <h3 class="section-title">Identity &amp; Citizenship</h3>
      <div class="form-grid grid-2">
        <div class="form-group">
          <label>Country *</label>
          <select id="countrySelect" name="country" onchange="handleCountryChange()" required>
            <?php foreach ($countries as $c): ?>
              <option value="<?= e($c) ?>" <?= old('country', 'Nepal') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" id="identityDocGroup">
          <label id="identityDocLabel">Citizenship No. *</label>
          <input type="text" id="identityDocInput" name="identity_no" placeholder="e.g. 12-01-78-12345" value="<?= old('identity_no') ?>" required>
        </div>
      </div>
    </div>

    <!-- Address Details -->
    <div class="form-section">
      <h3 class="section-title">Residential Address</h3>
      <div class="form-grid grid-3">
        <div class="form-group">
          <label>Province *</label>
          <select id="provinceSelect" name="province" onchange="handleProvinceChange()" required>
            <option value="">Select Province</option>
          </select>
        </div>
        <div class="form-group">
          <label>District *</label>
          <select id="districtSelect" name="district" onchange="handleDistrictChange()" required>
            <option value="">Select District</option>
          </select>
        </div>
        <div class="form-group">
          <label>Municipality / Rural Municipality *</label>
          <select id="municipalitySelect" name="municipality" required>
            <option value="">Select Municipality</option>
          </select>
        </div>
      </div>

      <div class="form-grid grid-2">
        <div class="form-group">
          <label>Tole Name *</label>
          <input type="text" name="tole" placeholder="e.g. Adarsh Nagar" value="<?= old('tole') ?>" required>
        </div>
        <div class="form-group">
          <label>Ward No. *</label>
          <input type="number" name="ward_no" min="1" max="35" placeholder="e.g. 10" value="<?= old('ward_no') ?>" required>
        </div>
      </div>
    </div>

    <!-- Authority Specific Fields (Hidden by default) -->
    <div class="form-section authority-only<?= $selRole === 'authority' ? ' active' : '' ?>" id="authorityFields">
      <h3 class="section-title">Authority &amp; Official Details</h3>
      <div class="form-grid grid-2">
        <div class="form-group">
          <label>Department / Agency *</label>
          <select id="authDeptInput" name="auth_department">
            <option value="">Select Agency</option>
            <option value="police"     <?= old('auth_department') === 'police' ? 'selected' : '' ?>>Nepal Police</option>
            <option value="fire"       <?= old('auth_department') === 'fire' ? 'selected' : '' ?>>Fire Brigade</option>
            <option value="ambulance"  <?= old('auth_department') === 'ambulance' ? 'selected' : '' ?>>Ambulance Services</option>
            <option value="municipality" <?= old('auth_department') === 'municipality' ? 'selected' : '' ?>>Municipality Emergency Unit</option>
            <option value="disaster"   <?= old('auth_department') === 'disaster' ? 'selected' : '' ?>>Disaster Management (NEOC)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Department / Officer ID *</label>
          <input type="text" id="authDeptIdInput" name="auth_officer_id" placeholder="Official Badge/ID Number" value="<?= old('auth_officer_id') ?>">
        </div>
      </div>

      <div class="form-grid grid-2">
        <div class="form-group">
          <label>Official Designations / Rank *</label>
          <input type="text" name="auth_designation" placeholder="e.g. Inspector, Control Officer" value="<?= old('auth_designation') ?>">
        </div>
        <div class="form-group">
          <label>Official Department Email *</label>
          <input type="email" name="auth_official_email" placeholder="official@police.gov.np" value="<?= old('auth_official_email') ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Department Address *</label>
        <input type="text" name="auth_address" placeholder="Station/Headquarters Street Address, City" value="<?= old('auth_address') ?>">
      </div>
    </div>

    <div class="form-footer">
      <button type="submit" class="btn-submit">Register Account</button>

      <p class="login-hint">Already have an account? <a href="<?= url('auth/login.php') ?>">Login here</a></p>
    </div>
  </form>
</div>

<script src="<?= asset('assets/js/nepal_locations_data.js') ?>"></script>
<script src="<?= asset('assets/js/register.js') ?>"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
