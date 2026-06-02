<?php

declare(strict_types=1);

namespace App\Modules\Jobs;

use App\Core\Database;

final class JobsRepository
{
    public function __construct(private Database $db) {}

    // ------------------------------------------------------------------ //
    //  Job Categories
    // ------------------------------------------------------------------ //

    public function allCategories(): array
    {
        return $this->db->fetchAll(
            'SELECT jc.*, COUNT(j.id) AS job_count
             FROM job_categories jc
             LEFT JOIN jobs j ON j.job_category_id = jc.id
             GROUP BY jc.id ORDER BY jc.sort_order ASC, jc.name ASC'
        );
    }

    public function findCategory(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM job_categories WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function createCategory(array $data): int
    {
        $this->db->execute(
            'INSERT INTO job_categories (name, slug, description, icon, is_active, sort_order)
             VALUES (:name, :slug, :description, :icon, :is_active, :sort_order)',
            $data
        );
        return (int) $this->db->lastInsertId();
    }

    public function updateCategory(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE job_categories SET name = :name, slug = :slug, description = :description,
             icon = :icon, is_active = :is_active, sort_order = :sort_order WHERE id = :id',
            array_merge($data, ['id' => $id])
        );
    }

    public function deleteCategory(int $id): void
    {
        $this->db->execute('DELETE FROM job_categories WHERE id = :id', ['id' => $id]);
    }

    // ------------------------------------------------------------------ //
    //  Jobs
    // ------------------------------------------------------------------ //

    public function listJobs(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'j.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'j.job_category_id = :cat';
            $params['cat'] = $filters['category_id'];
        }
        if (!empty($filters['job_type'])) {
            $where[] = 'j.job_type = :jtype';
            $params['jtype'] = $filters['job_type'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(j.title LIKE :q OR j.company_name LIKE :q OR j.department_name LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        return $this->db->fetchAll(
            'SELECT j.*, jc.name AS category_name,
                    (SELECT COUNT(*) FROM job_applications a WHERE a.job_id = j.id) AS application_count
             FROM jobs j
             LEFT JOIN job_categories jc ON jc.id = j.job_category_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY j.created_at DESC',
            $params
        );
    }

    public function findJob(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT j.*, jc.name AS category_name
             FROM jobs j LEFT JOIN job_categories jc ON jc.id = j.job_category_id
             WHERE j.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function createJob(array $data): int
    {
        $this->db->execute(
            'INSERT INTO jobs (
                job_category_id, title, slug, company_name, branch_name, department_name,
                location_city, location_country, job_type, experience_level,
                min_experience_years, max_experience_years,
                min_salary, max_salary, salary_currency, salary_visible,
                description, requirements, responsibilities, benefits,
                skills_required, education_required,
                positions_count, deadline, status, is_featured,
                created_by_hr_user_id, published_at
             ) VALUES (
                :job_category_id, :title, :slug, :company_name, :branch_name, :department_name,
                :location_city, :location_country, :job_type, :experience_level,
                :min_experience_years, :max_experience_years,
                :min_salary, :max_salary, :salary_currency, :salary_visible,
                :description, :requirements, :responsibilities, :benefits,
                :skills_required, :education_required,
                :positions_count, :deadline, :status, :is_featured,
                :created_by_hr_user_id, :published_at
             )',
            $data
        );
        return (int) $this->db->lastInsertId();
    }

    public function updateJob(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE jobs SET
                job_category_id = :job_category_id, title = :title, slug = :slug,
                company_name = :company_name, branch_name = :branch_name,
                department_name = :department_name,
                location_city = :location_city, location_country = :location_country,
                job_type = :job_type, experience_level = :experience_level,
                min_experience_years = :min_experience_years,
                max_experience_years = :max_experience_years,
                min_salary = :min_salary, max_salary = :max_salary,
                salary_currency = :salary_currency, salary_visible = :salary_visible,
                description = :description, requirements = :requirements,
                responsibilities = :responsibilities, benefits = :benefits,
                skills_required = :skills_required, education_required = :education_required,
                positions_count = :positions_count, deadline = :deadline,
                status = :status, is_featured = :is_featured, published_at = :published_at
             WHERE id = :id',
            array_merge($data, ['id' => $id])
        );
    }

    public function updateJobStatus(int $id, string $status): void
    {
        $closedAt = $status === 'closed' ? date('Y-m-d H:i:s') : null;
        $publishedAt = $status === 'open' ? date('Y-m-d H:i:s') : null;
        $this->db->execute(
            'UPDATE jobs SET status = :status,
             closed_at = COALESCE(:closed_at, closed_at),
             published_at = COALESCE(published_at, :published_at)
             WHERE id = :id',
            ['status' => $status, 'closed_at' => $closedAt, 'published_at' => $publishedAt, 'id' => $id]
        );
    }

    public function deleteJob(int $id): void
    {
        $this->db->execute('DELETE FROM jobs WHERE id = :id', ['id' => $id]);
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM jobs WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != :eid';
            $params['eid'] = $excludeId;
        }
        return $this->db->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    // ------------------------------------------------------------------ //
    //  Applications (HR view)
    // ------------------------------------------------------------------ //

    public function listApplications(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (isset($filters['job_id'])) {
            if ($filters['job_id'] === 'bank') {
                $where[] = 'a.job_id IS NULL';
            } elseif ($filters['job_id'] !== '') {
                $where[] = 'a.job_id = :jid';
                $params['jid'] = $filters['job_id'];
            }
        }
        if (!empty($filters['status'])) {
            $where[] = 'a.status = :astatus';
            $params['astatus'] = $filters['status'];
        }
        if (!empty($filters['rating'])) {
            $where[] = 'a.hr_rating = :rating';
            $params['rating'] = $filters['rating'];
        }
        if (!empty($filters['nationality'])) {
            $where[] = 'p.nationality = :nat';
            $params['nat'] = $filters['nationality'];
        }
        if (!empty($filters['country'])) {
            $where[] = 'p.country = :pcountry';
            $params['pcountry'] = $filters['country'];
        }
        if (!empty($filters['relocate'])) {
            $where[] = 'p.willing_to_relocate = :reloc';
            $params['reloc'] = (int) $filters['relocate'];
        }
        if (!empty($filters['min_exp'])) {
            $where[] = 'p.years_of_experience >= :minexp';
            $params['minexp'] = $filters['min_exp'];
        }
        if (!empty($filters['max_exp'])) {
            $where[] = 'p.years_of_experience <= :maxexp';
            $params['maxexp'] = $filters['max_exp'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(a.submitted_at) >= :dfrom';
            $params['dfrom'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(a.submitted_at) <= :dto';
            $params['dto'] = $filters['date_to'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(p.first_name LIKE :q OR p.last_name LIKE :q OR s.email LIKE :q OR p.current_job_title LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['job_type'])) {
            $where[] = 'j.job_type = :jtype';
            $params['jtype'] = $filters['job_type'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'j.job_category_id = :catid';
            $params['catid'] = $filters['category_id'];
        }
        if (!empty($filters['experience_level'])) {
            $where[] = 'j.experience_level = :elevel';
            $params['elevel'] = $filters['experience_level'];
        }

        return $this->db->fetchAll(
            'SELECT a.id, a.job_seeker_id, a.job_id, a.status, a.hr_rating,
                    a.submitted_at, a.reviewed_at,
                    s.username, s.email,
                    p.first_name, p.last_name, p.nationality, p.country,
                    p.current_job_title, p.years_of_experience,
                    p.expected_salary, p.salary_currency,
                    p.willing_to_relocate, p.cv_file_path, p.cv_original_name, p.photo_path,
                    j.title AS job_title, j.job_type, j.experience_level,
                    jc.name AS category_name
             FROM job_applications a
             INNER JOIN job_seekers s ON s.id = a.job_seeker_id
             LEFT JOIN job_seeker_profiles p ON p.job_seeker_id = a.job_seeker_id
             LEFT JOIN jobs j ON j.id = a.job_id
             LEFT JOIN job_categories jc ON jc.id = j.job_category_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.submitted_at DESC',
            $params
        );
    }

    public function findApplication(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT a.*, s.username, s.email, s.created_at AS seeker_registered_at,
                    p.*,
                    j.title AS job_title, j.job_type, j.experience_level, j.location_city, j.location_country,
                    jc.name AS category_name
             FROM job_applications a
             INNER JOIN job_seekers s ON s.id = a.job_seeker_id
             LEFT JOIN job_seeker_profiles p ON p.job_seeker_id = a.job_seeker_id
             LEFT JOIN jobs j ON j.id = a.job_id
             LEFT JOIN job_categories jc ON jc.id = j.job_category_id
             WHERE a.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function getApplicantSections(int $seekerId): array
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

    public function updateApplicationStatus(int $id, string $status, int $hrUserId, string $notes, ?int $rating): void
    {
        $old = $this->db->fetchValue('SELECT status FROM job_applications WHERE id = :id', ['id' => $id]);

        $this->db->execute(
            'UPDATE job_applications SET status = :status, hr_notes = :notes,
             hr_rating = :rating, reviewed_by_hr_user_id = :uid, reviewed_at = NOW()
             WHERE id = :id',
            ['status' => $status, 'notes' => $notes, 'rating' => $rating, 'uid' => $hrUserId, 'id' => $id]
        );

        // Log history
        $this->db->execute(
            'INSERT INTO application_status_history (application_id, old_status, new_status, changed_by_hr_user_id, notes)
             VALUES (:app_id, :old, :new, :uid, :notes)',
            ['app_id' => $id, 'old' => $old, 'new' => $status, 'uid' => $hrUserId, 'notes' => $notes]
        );
    }

    public function getStatusHistory(int $applicationId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM application_status_history WHERE application_id = :id ORDER BY changed_at DESC',
            ['id' => $applicationId]
        );
    }

    public function summaryCounts(): array
    {
        return [
            'total_jobs'         => (int) $this->db->fetchValue('SELECT COUNT(*) FROM jobs'),
            'open_jobs'          => (int) $this->db->fetchValue('SELECT COUNT(*) FROM jobs WHERE status = \'open\''),
            'total_applications' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM job_applications'),
            'new_applications'   => (int) $this->db->fetchValue('SELECT COUNT(*) FROM job_applications WHERE status = \'new\''),
            'job_bank'           => (int) $this->db->fetchValue('SELECT COUNT(*) FROM job_applications WHERE job_id IS NULL'),
            'total_seekers'      => (int) $this->db->fetchValue('SELECT COUNT(*) FROM job_seekers'),
        ];
    }
}
