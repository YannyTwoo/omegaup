<?php

/**
 * ContestController
 * 
 */
class ContestController extends Controller {

	private static $private_users_list;
	private static $hasPrivateUsers;
	private static $problems;

	/**
	 * Creates a new contest
	 * 
	 * @param Request $r
	 * @return array
	 * @throws DuplicatedEntryInDatabaseException
	 * @throws InvalidDatabaseOperation
	 */
	public static function apiCreate(Request $r) {

		// Authenticate user
		self::authenticateRequest($r);		
		
		// Validate request
		self::validateCreateRequest($r);

		// Create and populate a new Contests object
		$contest = new Contests();

		$contest->setPublic($r["public"]);
		$contest->setTitle($r["title"]);
		$contest->setDescription($r["description"]);
		$contest->setStartTime(gmdate('Y-m-d H:i:s', $r["start_time"]));
		$contest->setFinishTime(gmdate('Y-m-d H:i:s', $r["finish_time"]));
		$contest->setWindowLength($r["window_length"] == "NULL" ? NULL : $r["window_length"]);
		$contest->setDirectorId($r["current_user_id"]);
		$contest->setRerunId(0); // NYI
		$contest->setAlias($r["alias"]);
		$contest->setScoreboard($r["scoreboard"]);
		$contest->setPointsDecayFactor($r["points_decay_factor"]);
		$contest->setPartialScore($r["partial_score"]);
		$contest->setSubmissionsGap($r["submissions_gap"]);
		$contest->setFeedback($r["feedback"]);
		$contest->setPenalty(max(0, intval($r["penalty"])));
		$contest->setPenaltyTimeStart($r["penalty_time_start"]);
		$contest->setPenaltyCalcPolicy($r["penalty_calc_policy"]);

		if (!is_null($r["show_scoreboard_after"])) {
			$contest->setShowScoreboardAfter($r["show_scoreboard_after"]);
		} else {
			$contest->setShowScoreboardAfter("1");
		}


		// Push changes
		try {
			// Begin a new transaction
			ContestsDAO::transBegin();

			// Save the contest object with data sent by user to the database
			ContestsDAO::save($contest);

			// If the contest is private, add the list of allowed users
			if ($r["public"] == 0 && self::$hasPrivateUsers) {
				foreach (self::$private_users_list as $userkey) {
					// Create a temp DAO for the relationship
					$temp_user_contest = new ContestsUsers(array(
								"contest_id" => $contest->getContestId(),
								"user_id" => $userkey,
								"access_time" => "0000-00-00 00:00:00",
								"score" => 0,
								"time" => 0
							));

					// Save the relationship in the DB
					ContestsUsersDAO::save($temp_user_contest);
				}
			}

			if (!is_null($r['problems'])) {
				foreach (self::$problems as $problem) {
					$contest_problem = new ContestProblems(array(
								'contest_id' => $contest->getContestId(),
								'problem_id' => $problem['id'],
								'points' => $problem['points']
							));

					ContestProblemsDAO::save($contest_problem);
				}
			}

			// End transaction transaction
			ContestsDAO::transEnd();
		} catch (Exception $e) {
			// Operation failed in the data layer, rollback transaction 
			ContestsDAO::transRollback();

			// Alias may be duplicated, 1062 error indicates that
			if (strpos($e->getMessage(), "1062") !== FALSE) {
				throw new DuplicatedEntryInDatabaseException("alias already exists. Please choose a different alias.", $e);
			} else {
				throw new InvalidDatabaseOperation($e);
			}
		}

		Logger::log("New Contest Created: " . $r['alias']);
		return array("status" => "ok");
	}

	/**
	 * Validates that Request contains expected data. In case of error, this 
	 * function throws.
	 * 
	 * @param Request $r
	 * @throws InvalidParameterException
	 */
	private static function validateCreateRequest(Request $r) {

		Validators::isStringNonEmpty($r["title"], "title");
		Validators::isStringNonEmpty($r["description"], "description");

		Validators::isNumber($r["start_time"], "start_time");
		Validators::isNumber($r["finish_time"], "finish_time");
		if ($r["start_time"] > $r["finish_time"]) {
			throw new InvalidParameterException("start_time cannot be after finish_time");
		}

		// Calculate contest length:
		$contest_length = $r["finish_time"] - $r["start_time"];

		// Window_length is optional        
		Validators::isNumberInRange(
				$r["window_length"], "window_length", 0, floor($contest_length) / 60, false
		);

		Validators::isInEnum($r["public"], "public", array("0", "1"));
		Validators::isStringOfMaxLength($r["alias"], "alias", 32);
		Validators::isNumberInRange($r["scoreboard"], "scoreboard", 0, 100);
		Validators::isNumberInRange($r["points_decay_factor"], "points_decay_factor", 0, 1);
		Validators::isInEnum($r["partial_score"], "partial_score", array("0", "1"));
		Validators::isNumberInRange($r["submissions_gap"], "submissions_gap", 0, $contest_length);

		Validators::isInEnum($r["feedback"], "feedback", array("no", "yes", "partial"));
		Validators::isInEnum($r["penalty_time_start"], "penalty_time_start", array("contest", "problem", "none"));
		Validators::isInEnum($r["penalty_calc_policy"], "penalty_calc_policy", array("sum", "max"));


		if ($r["public"] == 0 && !is_null($r["private_users"])) {
			// Validate that the request is well-formed
			//  @todo move $this
			self::$private_users_list= json_decode($r["private_users"]);
			if (is_null(self::$private_users_list)) {
				throw new InvalidParameterException("private_users" . Validators::IS_INVALID);
			}

			// Validate that all users exists in the DB
			foreach (self::$private_users_list as $userkey) {
				if (is_null(UsersDAO::getByPK($userkey))) {
					throw new InvalidParameterException("private_users contains a user that doesn't exists");
				}
			}

			// Turn on flag to add private users later
			self::$hasPrivateUsers = true;
		}

		// Problems is optional
		if (!is_null($r['problems'])) {
			self::$problems = array();

			foreach (json_decode($r['problems']) as $problem) {
				$p = ProblemsDAO::getByAlias($problem->problem);
				array_push(self::$problems, array(
					'id' => $p->getProblemId(),
					'alias' => $problem->problem,
					'points' => $problem->points
				));
			}
		}

		Validators::isInEnum($r["show_scoreboard_after"], "show_scoreboard_after", array("0", "1"), false);
	}

}