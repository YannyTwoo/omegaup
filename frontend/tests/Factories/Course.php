<?php

namespace OmegaUp\Test\Factories;

class Course {
    /**
     * @return array{admin: \OmegaUp\DAO\VO\Identities, course_alias: string, request: \OmegaUp\Request}
     */
    public static function createCourse(
        \OmegaUp\DAO\VO\Identities $admin = null,
        \OmegaUp\Test\ScopedLoginToken $adminLogin = null,
        bool $public = false,
        string $requestsUserInformation = 'no',
        string $showScoreboard = 'false'
    ): array {
        if (is_null($admin)) {
            ['user' => $user, 'identity' => $admin] = \OmegaUp\Test\Factories\User::createUser();
            $adminLogin = \OmegaUp\Test\ControllerTestCase::login($admin);
        }
        if ($public != false) {
            $curatorGroup = \OmegaUp\DAO\Groups::findByAlias(
                \OmegaUp\Authorization::COURSE_CURATOR_GROUP_ALIAS
            );
            if (is_null($curatorGroup)) {
                throw new \OmegaUp\Exceptions\NotFoundException(
                    'courseGroupNotFound'
                );
            }

            \OmegaUp\DAO\GroupsIdentities::create(new \OmegaUp\DAO\VO\GroupsIdentities([
                'group_id' => $curatorGroup->group_id,
                'identity_id' => $admin->identity_id,
            ]));
        }

        $courseAlias = \OmegaUp\Test\Utils::createRandomString();
        if (is_null($adminLogin)) {
            throw new \OmegaUp\Exceptions\NotFoundException();
        }

        $r = new \OmegaUp\Request([
            'auth_token' => $adminLogin->auth_token,
            'name' => \OmegaUp\Test\Utils::createRandomString(),
            'alias' => $courseAlias,
            'description' => \OmegaUp\Test\Utils::createRandomString(),
            'start_time' => (\OmegaUp\Time::get()),
            'finish_time' => (\OmegaUp\Time::get() + 120),
            'public' => $public,
            'requests_user_information' => $requestsUserInformation,
            'show_scoreboard' => $showScoreboard,
        ]);

        \OmegaUp\Controllers\Course::apiCreate($r);

        return [
            'request' => $r,
            'admin' => $admin,
            'course_alias' => $courseAlias,
        ];
    }

    /**
     * @return array{admin: \OmegaUp\DAO\VO\Identities, assignment: \OmegaUp\DAO\VO\Assignments|null, assignment_alias: string, course: \OmegaUp\DAO\VO\Courses, course_alias: string, problemset_id: int|null, request: \OmegaUp\Request}
     */
    public static function createCourseWithOneAssignment(
        \OmegaUp\DAO\VO\Identities $admin = null,
        \OmegaUp\Test\ScopedLoginToken $adminLogin = null,
        bool $public = false,
        string $requestsUserInformation = 'no',
        string $showScoreboard = 'false',
        int $startTimeDelay = 0
    ) {
        if (is_null($admin)) {
            ['user' => $user, 'identity' => $admin] = \OmegaUp\Test\Factories\User::createUser();
            $adminLogin = \OmegaUp\Test\ControllerTestCase::login($admin);
        }

        // Create the course
        $courseFactoryResult = self::createCourse(
            $admin,
            $adminLogin,
            $public,
            $requestsUserInformation,
            $showScoreboard
        );
        $courseAlias = $courseFactoryResult['course_alias'];

        // Create the assignment
        $assignmentAlias = \OmegaUp\Test\Utils::createRandomString();
        $course = \OmegaUp\DAO\Courses::getByAlias($courseAlias);
        if (is_null($course) || is_null($course->course_id)) {
            throw new \OmegaUp\Exceptions\NotFoundException('courseNotFound');
        }
        if (is_null($adminLogin)) {
            throw new \OmegaUp\Exceptions\NotFoundException();
        }

        $r = new \OmegaUp\Request([
            'auth_token' => $adminLogin->auth_token,
            'name' => \OmegaUp\Test\Utils::createRandomString(),
            'alias' => $assignmentAlias,
            'description' => \OmegaUp\Test\Utils::createRandomString(),
            'start_time' => \OmegaUp\Time::get() + $startTimeDelay,
            'finish_time' => \OmegaUp\Time::get() + 120,
            'course_alias' => $courseAlias,
            'assignment_type' => 'homework',
            'course' => $course,
        ]);
        $assignmentResult = \OmegaUp\Controllers\Course::apiCreateAssignment(
            $r
        );
        $assignment = \OmegaUp\DAO\Assignments::getByAliasAndCourse(
            $assignmentAlias,
            $course->course_id
        );
        if (is_null($assignment) || is_null($assignment->problemset_id)) {
            throw new \OmegaUp\Exceptions\NotFoundException(
                'assignmentNotFound'
            );
        }
        return [
            'course' => $course,
            'course_alias' => $courseAlias,
            'assignment_alias' => $assignmentAlias,
            'problemset_id' => $assignment->problemset_id,
            'assignment' => $assignment,
            'request' => $r,
            'admin' => $admin
        ];
    }

    /**
     * @return array{admin: \OmegaUp\DAO\VO\Identities, assignment_aliases: list<string>, course_alias: string}
     */
    public static function createCourseWithAssignments(
        int $nAssignments
    ): array {
        return self::createCourseWithNAssignmentsPerType([
            'homework' => $nAssignments
        ]);
    }

    /**
     * @param array{homework?: int, test?: int} $assignmentsPerType
     * @return array{admin: \OmegaUp\DAO\VO\Identities, assignment_aliases: list<string>, course_alias: string}
     */
    public static function createCourseWithNAssignmentsPerType(
        array $assignmentsPerType
    ): array {
        $courseFactoryResult = self::createCourse();
        $courseAlias = $courseFactoryResult['course_alias'];
        $admin = $courseFactoryResult['admin'];
        $adminLogin = \OmegaUp\Test\ControllerTestCase::login($admin);
        $assignmentAlias = [];

        foreach ($assignmentsPerType as $assignmentType => $count) {
            for ($i = 0; $i < $count; $i++) {
                $r = new \OmegaUp\Request([
                    'auth_token' => $adminLogin->auth_token,
                    'name' => \OmegaUp\Test\Utils::createRandomString(),
                    'alias' => \OmegaUp\Test\Utils::createRandomString(),
                    'description' => \OmegaUp\Test\Utils::createRandomString(),
                    'start_time' => (\OmegaUp\Time::get()),
                    'finish_time' => (\OmegaUp\Time::get() + 120),
                    'course_alias' => $courseAlias,
                    'assignment_type' => $assignmentType
                ]);

                $assignmentAlias[] = strval($r['alias']);
                \OmegaUp\Controllers\Course::apiCreateAssignment($r);
            }
        }

        return [
            'admin' => $admin,
            'course_alias' => $courseAlias,
            'assignment_aliases' => $assignmentAlias
        ];
    }

    /**
     * Add a Student to a course
     * @param array{admin: \OmegaUp\DAO\VO\Identities, assignment: \OmegaUp\DAO\VO\Assignments|null, assignment_alias: string, course: \OmegaUp\DAO\VO\Courses, course_alias: string, problemset_id: int|null, request: \OmegaUp\Request} $courseData
     * @param ?\OmegaUp\DAO\VO\Identities $student
     */
    public static function addStudentToCourse(
        array $courseData,
        ?\OmegaUp\DAO\VO\Identities $student = null,
        ?\OmegaUp\Test\ScopedLoginToken $login = null
    ): \OmegaUp\DAO\VO\Identities {
        if (is_null($student)) {
            ['user' => $user, 'identity' => $student] = \OmegaUp\Test\Factories\User::createUser();
        }

        $course = \OmegaUp\DAO\Courses::getByAlias($courseData['course_alias']);
        if (is_null($course) || is_null($course->group_id)) {
            throw new \OmegaUp\Exceptions\NotFoundException('courseNotFound');
        }
        $group = \OmegaUp\DAO\Groups::getByPK($course->group_id);
        if (is_null($group) || is_null($group->alias)) {
            throw new \OmegaUp\Exceptions\NotFoundException(
                'courseGroupNotFound'
            );
        }
        if (is_null($login)) {
            $login = \OmegaUp\Test\ControllerTestCase::login(
                $courseData['admin']
            );
        }
        \OmegaUp\Controllers\Group::apiAddUser(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'usernameOrEmail' => $student->username,
            'group_alias' => $group->alias
        ]));

        return $student;
    }

    /**
     * @param list<array{author: \OmegaUp\DAO\VO\Identities, authorUser: \OmegaUp\DAO\VO\Users, problem: \OmegaUp\DAO\VO\Problems, request: \OmegaUp\Request}> $problems
     * @return list<array{status: 'ok'}>
     */
    public static function addProblemsToAssignment(
        \OmegaUp\Test\ScopedLoginToken $login,
        string $courseAlias,
        string $assignmentAlias,
        array $problems
    ): array {
        $responses = [];
        foreach ($problems as $problem) {
            // Add a problem to the assignment
            $responses[] = \OmegaUp\Controllers\Course::apiAddProblem(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'course_alias' => $courseAlias,
                'assignment_alias' => $assignmentAlias,
                'problem_alias' => $problem['problem']->alias,
            ]));
        }

        return $responses;
    }

    /**
     * @param array{course_alias: string} $courseData
     * @param \OmegaUp\DAO\VO\Identities[] $students
     * @param string[] $assignmentAliases
     * @param array<string, list<array{author: \OmegaUp\DAO\VO\Identities, authorUser: \OmegaUp\DAO\VO\Users, problem: \OmegaUp\DAO\VO\Problems, request: \OmegaUp\Request}>> $problemAssignmentsMap
     * @return array<string, array<string, int>>
     */
    public static function submitRunsToAssignmentsInCourse(
        array $courseData,
        array $students,
        array $assignmentAliases,
        array $problemAssignmentsMap
    ): array {
        $course = \OmegaUp\DAO\Courses::getByAlias($courseData['course_alias']);
        if (is_null($course) || is_null($course->course_id)) {
            throw new \OmegaUp\Exceptions\NotFoundException('courseNotFound');
        }
        $expectedScores = [];
        foreach ($students as $s => $student) {
            if (is_null($student->username)) {
                throw new \OmegaUp\Exceptions\NotFoundException(
                    'userNotFound'
                );
            }
            $studentUsername = $student->username;
            $expectedScores[$studentUsername] = [];
            $studentLogin = \OmegaUp\Test\ControllerTestCase::login($student);

            // Loop through all problems inside assignments created
            $p = 0;
            foreach ($assignmentAliases as $assignmentAlias) {
                $assignment = \OmegaUp\DAO\Assignments::getByAliasAndCourse(
                    $assignmentAlias,
                    $course->course_id
                );
                if (
                    is_null($assignment) ||
                    is_null($assignment->problemset_id)
                ) {
                    throw new \OmegaUp\Exceptions\NotFoundException(
                        'assignmentNotFound'
                    );
                }

                $expectedScores[$studentUsername][$assignmentAlias] = 0;

                foreach ($problemAssignmentsMap[$assignmentAlias] as $problemData) {
                    $p++;
                    if (intval($s) % 2 == $p % 2) {
                        // PA run
                        $runResponsePA = \OmegaUp\Controllers\Run::apiCreate(new \OmegaUp\Request([
                            'auth_token' => $studentLogin->auth_token,
                            'problemset_id' => $assignment->problemset_id,
                            'problem_alias' => $problemData['request']['problem_alias'],
                            'language' => 'c',
                            'source' => "#include <stdio.h>\nint main() { printf(\"3\"); return 0; }",
                        ]));
                        \OmegaUp\Test\Factories\Run::gradeRun(
                            null /*runData*/,
                            0.5,
                            'PA',
                            null,
                            $runResponsePA['guid']
                        );
                        $expectedScores[$studentUsername][$assignmentAlias] += 50;

                        if ((intval($s) + $p) % 3 == 0) {
                            // 100 pts run
                            $runResponseAC = \OmegaUp\Controllers\Run::apiCreate(new \OmegaUp\Request([
                                'auth_token' => $studentLogin->auth_token,
                                'problemset_id' => $assignment->problemset_id,
                                'problem_alias' => $problemData['request']['problem_alias'],
                                'language' => 'c',
                                'source' => "#include <stdio.h>\nint main() { printf(\"3\"); return 0; }",
                            ]));
                            \OmegaUp\Test\Factories\Run::gradeRun(
                                null /*runData*/,
                                1,
                                'AC',
                                null,
                                $runResponseAC['guid']
                            );
                            $expectedScores[$studentUsername][$assignmentAlias] += 50;
                        }
                    }
                }
            }
        }

        return $expectedScores;
    }

    /**
     * @param array{admin: \OmegaUp\DAO\VO\Identities, assignment: \OmegaUp\DAO\VO\Assignments|null, assignment_alias: string, course: \OmegaUp\DAO\VO\Courses, course_alias: string, problemset_id: int|null, request: \OmegaUp\Request} $courseAssignmentData
     */
    public static function openCourse(
        array $courseAssignmentData,
        \OmegaUp\DAO\VO\Identities $user
    ): void {
        // Log in as course admin
        $login = \OmegaUp\Test\ControllerTestCase::login($user);

        // Call api
        \OmegaUp\Controllers\Course::apiIntroDetails(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'course_alias' => $courseAssignmentData['request']['course_alias'],
        ]));
    }

    /**
     * @param array{admin: \OmegaUp\DAO\VO\Identities, assignment: \OmegaUp\DAO\VO\Assignments|null, assignment_alias: string, course: \OmegaUp\DAO\VO\Courses, course_alias: string, problemset_id: int|null, request: \OmegaUp\Request} $courseAssignmentData
     */
    public static function openAssignmentCourse(
        array $courseAssignmentData,
        \OmegaUp\DAO\VO\Identities $user
    ): void {
        // Log in as course adminy
        $login = \OmegaUp\Test\ControllerTestCase::login($user);

        // Call api
        \OmegaUp\Controllers\Course::apiIntroDetails(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'course_alias' => $courseAssignmentData['request']['course_alias'],
            'assignment_alias' => $courseAssignmentData['request']['assignment_alias'],
        ]));
    }

    /**
     * @param array{admin: \OmegaUp\DAO\VO\Identities, assignment: \OmegaUp\DAO\VO\Assignments|null, assignment_alias: string, course: \OmegaUp\DAO\VO\Courses, course_alias: string, problemset_id: int|null, request: \OmegaUp\Request} $courseAssignmentData
     * @param array{problem: \OmegaUp\DAO\VO\Problems, author: \OmegaUp\DAO\VO\Identities, request: \OmegaUp\Request, authorUser: \OmegaUp\DAO\VO\Users} $problemData
     */
    public static function openProblemInCourseAssignment(
        array $courseAssignmentData,
        array $problemData,
        \OmegaUp\DAO\VO\Identities $user
    ): void {
        // Log in the user
        $login = \OmegaUp\Test\ControllerTestCase::login($user);

        // Call api
        \OmegaUp\Controllers\Problem::apiDetails(new \OmegaUp\Request([
            'course_alias' => $courseAssignmentData['request']['course_alias'],
            'assignment_alias' => $courseAssignmentData['request']['assignment_alias'],
            'problem_alias' => $problemData['request']['problem_alias'],
            'auth_token' => $login->auth_token,
        ]));
    }
}