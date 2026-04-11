<?php

declare(strict_types=1);

namespace App\Modules\Careers;

use App\Core\Database;

final class CareersRepository
{
    public function __construct(private Database $db) {}

    // ------------------------------------------------------------------ //
    //  Seeker accounts
    // ------------------------------------------------------------------ //

    public function findSeekerByEmail(string $email): ?array
    {
        return $this->db->fetch('SELECT * FROM job_seekers WHERE email = :email LIMIT 1', ['email' => $email]);
    }

    public function findSeekerByUsername(string $username): ?array
    {
        return $this->db->fetch('SELECT * FROM job_seekers WHERE username = :u LIMIT 1', ['u' => $username]);
    }

    public function findSeekerById(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM job_seekers WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function createSeeker(string $username, string $email, string $passwordHash): int
    {
        $this->db->execute(
            'INSERT INTO job_seekers (username, email, password_hash) VALUES (:u, :e, :p)',
            ['u' => $username, 'e' => $email, 'p' => $passwordHash]
        );
        $id = (int) $this->db->lastInsertId();
        // Create empty profile row
        $this->db->execute('INSERT INTO job_seeker_profiles (job_seeker_id) VALUES (:id)', ['id' => $id]);
        return $id;
    }

    public function saveOtp(int $seekerId, string $code, \DateTimeImmutable $expires, int $sentCount, \DateTimeImmutable $windowStart): void
    {
        $this->db->execute(
            'UPDATE job_seekers
             SET otp_code = :code, otp_expires_at = :exp, otp_attempts = 0,
                 otp_sent_count = :cnt, otp_sent_window_start = :win
             WHERE id = :id',
            [
                'code' => $code,
                'exp'  => $expires->format('Y-m-d H:i:s'),
                'cnt'  => $sentCount,
                'win'  => $windowStart->format('Y-m-d H:i:s'),
                'id'   => $seekerId,
            ]
        );
    }

    public function incrementOtpAttempts(int $seekerId): void
    {
        $this->db->execute(
            'UPDATE job_seekers SET otp_attempts = otp_attempts + 1 WHERE id = :id',
            ['id' => $seekerId]
        );
    }

    public function clearOtp(int $seekerId): void
    {
        $this->db->execute(
            'UPDATE job_seekers
             SET otp_code = NULL, otp_expires_at = NULL, otp_attempts = 0,
                 last_login_at = NOW(), email_verified_at = COALESCE(email_verified_at, NOW())
             WHERE id = :id',
            ['id' => $seekerId]
        );
    }

    // ------------------------------------------------------------------ //
    //  Profile
    // ------------------------------------------------------------------ //

    public function getProfile(int $seekerId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM job_seeker_profiles WHERE job_seeker_id = :id LIMIT 1',
            ['id' => $seekerId]
        );

        if ($row === null) {
            return null;
        }

        foreach (['phone', 'mobile', 'whatsapp_number', 'date_of_birth'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = decrypt_field($row[$field]);
            }
        }

        return $row;
    }

    public function updatePersonal(int $seekerId, array $data): void
    {
        $encrypted = array_merge($data, [
            'phone'           => encrypt_field($data['phone'] ?? null),
            'mobile'          => encrypt_field($data['mobile'] ?? null),
            'whatsapp_number' => encrypt_field($data['whatsapp_number'] ?? null),
            'date_of_birth'   => encrypt_field($data['date_of_birth'] ?? null),
        ]);

        $this->db->execute(
            'UPDATE job_seeker_profiles SET
                first_name = :first_name, last_name = :last_name, middle_name = :middle_name,
                date_of_birth = :date_of_birth, gender = :gender,
                nationality = :nationality, second_nationality = :second_nationality,
                phone = :phone, mobile = :mobile, whatsapp_number = :whatsapp_number,
                address_line_1 = :address_line_1, address_line_2 = :address_line_2,
                city = :city, state = :state, country = :country, postal_code = :postal_code,
                linkedin_url = :linkedin_url, portfolio_url = :portfolio_url,
                github_url = :github_url, website_url = :website_url
             WHERE job_seeker_id = :job_seeker_id',
            array_merge($encrypted, ['job_seeker_id' => $seekerId])
        );
    }

    public function updateProfessional(int $seekerId, array $data): void
    {
        $this->db->execute(
            'UPDATE job_seeker_profiles SET
                professional_summary = :professional_summary,
                current_job_title = :current_job_title, current_employer = :current_employer,
                years_of_experience = :years_of_experience,
                expected_salary = :expected_salary, salary_currency = :salary_currency,
                notice_period_days = :notice_period_days,
                available_from = :available_from,
                willing_to_relocate = :willing_to_relocate,
                willing_to_travel = :willing_to_travel,
                employment_type_preference = :employment_type_preference
             WHERE job_seeker_id = :job_seeker_id',
            array_merge($data, ['job_seeker_id' => $seekerId])
        );
    }

    public function updatePhoto(int $seekerId, string $path): void
    {
        $this->db->execute(
            'UPDATE job_seeker_profiles SET photo_path = :p WHERE job_seeker_id = :id',
            ['p' => $path, 'id' => $seekerId]
        );
    }

    public function updateCv(int $seekerId, string $path, string $originalName): void
    {
        $this->db->execute(
            'UPDATE job_seeker_profiles
             SET cv_file_path = :p, cv_original_name = :n, cv_uploaded_at = NOW()
             WHERE job_seeker_id = :id',
            ['p' => $path, 'n' => $originalName, 'id' => $seekerId]
        );
    }

    // ------------------------------------------------------------------ //
    //  CV Sections
    // ------------------------------------------------------------------ //

    public function getSections(int $seekerId, string $type): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM job_seeker_sections
             WHERE job_seeker_id = :id AND type = :type
             ORDER BY display_order ASC, id ASC',
            ['id' => $seekerId, 'type' => $type]
        );
    }

    public function getAllSections(int $seekerId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM job_seeker_sections WHERE job_seeker_id = :id ORDER BY type, display_order ASC, id ASC',
            ['id' => $seekerId]
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['type']][] = $row;
        }
        return $grouped;
    }

    public function findSection(int $id, int $seekerId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM job_seeker_sections WHERE id = :id AND job_seeker_id = :sid LIMIT 1',
            ['id' => $id, 'sid' => $seekerId]
        );
    }

    public function addSection(int $seekerId, string $type, array $data): int
    {
        $maxOrder = (int) ($this->db->fetchValue(
            'SELECT MAX(display_order) FROM job_seeker_sections WHERE job_seeker_id = :id AND type = :t',
            ['id' => $seekerId, 't' => $type]
        ) ?? 0);

        $this->db->execute(
            'INSERT INTO job_seeker_sections
                (job_seeker_id, type, title, subtitle, data, start_date, end_date, is_current, display_order)
             VALUES
                (:job_seeker_id, :type, :title, :subtitle, :data, :start_date, :end_date, :is_current, :display_order)',
            [
                'job_seeker_id' => $seekerId,
                'type'          => $type,
                'title'         => $data['title'] ?? null,
                'subtitle'      => $data['subtitle'] ?? null,
                'data'          => isset($data['data']) ? json_encode($data['data']) : null,
                'start_date'    => $data['start_date'] ?: null,
                'end_date'      => $data['end_date'] ?: null,
                'is_current'    => (int) ($data['is_current'] ?? 0),
                'display_order' => $maxOrder + 1,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function updateSection(int $id, int $seekerId, array $data): void
    {
        $this->db->execute(
            'UPDATE job_seeker_sections SET
                title = :title, subtitle = :subtitle, data = :data,
                start_date = :start_date, end_date = :end_date, is_current = :is_current
             WHERE id = :id AND job_seeker_id = :sid',
            [
                'title'      => $data['title'] ?? null,
                'subtitle'   => $data['subtitle'] ?? null,
                'data'       => isset($data['data']) ? json_encode($data['data']) : null,
                'start_date' => $data['start_date'] ?: null,
                'end_date'   => $data['end_date'] ?: null,
                'is_current' => (int) ($data['is_current'] ?? 0),
                'id'         => $id,
                'sid'        => $seekerId,
            ]
        );
    }

    public function deleteSection(int $id, int $seekerId): void
    {
        $this->db->execute(
            'DELETE FROM job_seeker_sections WHERE id = :id AND job_seeker_id = :sid',
            ['id' => $id, 'sid' => $seekerId]
        );
    }

    public function reorderSections(int $seekerId, string $type, array $orderedIds): void
    {
        foreach ($orderedIds as $order => $sectionId) {
            $this->db->execute(
                'UPDATE job_seeker_sections SET display_order = :ord WHERE id = :id AND job_seeker_id = :sid AND type = :t',
                ['ord' => $order, 'id' => (int) $sectionId, 'sid' => $seekerId, 't' => $type]
            );
        }
    }

    // ------------------------------------------------------------------ //
    //  Jobs (public portal)
    // ------------------------------------------------------------------ //

    public function listOpenJobs(array $filters = []): array
    {
        $where = ['j.status = :status'];
        $params = ['status' => 'open'];

        if (!empty($filters['category'])) {
            $where[] = 'jc.slug = :cat';
            $params['cat'] = $filters['category'];
        }
        if (!empty($filters['job_type'])) {
            $where[] = 'j.job_type = :jtype';
            $params['jtype'] = $filters['job_type'];
        }
        if (!empty($filters['experience_level'])) {
            $where[] = 'j.experience_level = :elevel';
            $params['elevel'] = $filters['experience_level'];
        }
        if (!empty($filters['country'])) {
            $where[] = 'j.location_country = :lcountry';
            $params['lcountry'] = $filters['country'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(j.title LIKE :q OR j.description LIKE :q OR j.company_name LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql = 'SELECT j.*, jc.name AS category_name, jc.icon AS category_icon
                FROM jobs j
                LEFT JOIN job_categories jc ON jc.id = j.job_category_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY j.is_featured DESC, j.published_at DESC';

        return $this->db->fetchAll($sql, $params);
    }

    public function findJob(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT j.*, jc.name AS category_name FROM jobs j
             LEFT JOIN job_categories jc ON jc.id = j.job_category_id
             WHERE j.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function findJobBySlug(string $slug): ?array
    {
        return $this->db->fetch(
            'SELECT j.*, jc.name AS category_name, jc.icon AS category_icon FROM jobs j
             LEFT JOIN job_categories jc ON jc.id = j.job_category_id
             WHERE j.slug = :slug LIMIT 1',
            ['slug' => $slug]
        );
    }

    public function incrementJobViews(int $jobId): void
    {
        $this->db->execute('UPDATE jobs SET views_count = views_count + 1 WHERE id = :id', ['id' => $jobId]);
    }

    public function listCategories(): array
    {
        return $this->db->fetchAll(
            'SELECT jc.*, COUNT(j.id) AS job_count
             FROM job_categories jc
             LEFT JOIN jobs j ON j.job_category_id = jc.id AND j.status = \'open\'
             WHERE jc.is_active = 1
             GROUP BY jc.id ORDER BY jc.sort_order ASC, jc.name ASC'
        );
    }

    // ------------------------------------------------------------------ //
    //  Applications
    // ------------------------------------------------------------------ //

    public function hasApplied(int $seekerId, ?int $jobId): bool
    {
        $sql = $jobId === null
            ? 'SELECT id FROM job_applications WHERE job_seeker_id = :sid AND job_id IS NULL LIMIT 1'
            : 'SELECT id FROM job_applications WHERE job_seeker_id = :sid AND job_id = :jid LIMIT 1';

        $params = $jobId === null ? ['sid' => $seekerId] : ['sid' => $seekerId, 'jid' => $jobId];
        return $this->db->fetch($sql, $params) !== null;
    }

    public function createApplication(int $seekerId, ?int $jobId, string $coverLetter): int
    {
        $this->db->execute(
            'INSERT INTO job_applications (job_seeker_id, job_id, cover_letter, submitted_at)
             VALUES (:sid, :jid, :cl, NOW())',
            ['sid' => $seekerId, 'jid' => $jobId, 'cl' => $coverLetter]
        );
        return (int) $this->db->lastInsertId();
    }

    public function getSeekerApplications(int $seekerId): array
    {
        return $this->db->fetchAll(
            'SELECT a.*, j.title AS job_title, j.slug AS job_slug, j.company_name, j.location_city, j.location_country, j.job_type
             FROM job_applications a
             LEFT JOIN jobs j ON j.id = a.job_id
             WHERE a.job_seeker_id = :sid
             ORDER BY a.submitted_at DESC',
            ['sid' => $seekerId]
        );
    }

    public function findApplication(int $id, int $seekerId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM job_applications WHERE id = :id AND job_seeker_id = :sid LIMIT 1',
            ['id' => $id, 'sid' => $seekerId]
        );
    }

    public function withdrawApplication(int $id, int $seekerId): void
    {
        $this->db->execute(
            "UPDATE job_applications SET status = 'withdrawn'
             WHERE id = :id AND job_seeker_id = :sid AND status NOT IN ('hired','rejected')",
            ['id' => $id, 'sid' => $seekerId]
        );
    }

    // ------------------------------------------------------------------ //
    //  Completeness score helper
    // ------------------------------------------------------------------ //

    public function computeCompleteness(array $profile, array $sections): int
    {
        $score = 0;

        // Personal info — 15%
        if (!empty($profile['first_name']) && !empty($profile['last_name'])
            && !empty($profile['date_of_birth']) && !empty($profile['phone'])) {
            $score += 15;
        }

        // Professional summary — 10%
        if (!empty($profile['professional_summary'])) {
            $score += 10;
        }

        // At least 1 experience — 15%
        if (!empty($sections['experience'])) {
            $score += 15;
        }

        // At least 1 education — 10%
        if (!empty($sections['education'])) {
            $score += 10;
        }

        // At least 3 skills — 10%
        if (count($sections['skill'] ?? []) >= 3) {
            $score += 10;
        }

        // At least 1 language — 5%
        if (!empty($sections['language'])) {
            $score += 5;
        }

        // Photo — 5%
        if (!empty($profile['photo_path'])) {
            $score += 5;
        }

        // CV file — 20%
        if (!empty($profile['cv_file_path'])) {
            $score += 20;
        }

        // Salary + availability + employment type — 10%
        if (!empty($profile['expected_salary']) && !empty($profile['available_from'])
            && !empty($profile['employment_type_preference'])) {
            $score += 10;
        }

        return $score;
    }
}
