<?php

require __DIR__ . '/../app/bootstrap.php';

$router = new Router();

// Auth
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/login/azure', 'AuthController@azureRedirect');
$router->get('/login/callback', 'AuthController@azureCallback');
$router->get('/logout', 'AuthController@logout');

// Dashboard
$router->get('/', 'DashboardController@index', ['AuthMiddleware::handle']);
$router->get('/dashboard', 'DashboardController@index', ['AuthMiddleware::handle']);

// Jobs
$router->get('/jobs', 'JobController@index', ['AuthMiddleware::handle']);
$router->get('/jobs/create', 'JobController@create', ['AuthMiddleware::handle']);
$router->post('/jobs/create', 'JobController@storeStep1', ['AuthMiddleware::handle']);
$router->get('/jobs/create/pipeline', 'JobController@createPipeline', ['AuthMiddleware::handle']);
$router->post('/jobs/create/pipeline', 'JobController@storePipeline', ['AuthMiddleware::handle']);
$router->get('/jobs/create/questions', 'JobController@createQuestions', ['AuthMiddleware::handle']);
$router->post('/jobs/create/questions', 'JobController@storeQuestions', ['AuthMiddleware::handle']);
$router->get('/jobs/create/draft-message', 'JobController@createDraftMessage', ['AuthMiddleware::handle']);
$router->post('/jobs/create/draft-message', 'JobController@storeDraftMessage', ['AuthMiddleware::handle']);
$router->get('/jobs/create/candidates', 'JobController@createCandidates', ['AuthMiddleware::handle']);

$router->get('/jobs/edit/{id}', 'JobController@edit', ['AuthMiddleware::handle']);
$router->post('/jobs/edit/{id}', 'JobController@updateStep1', ['AuthMiddleware::handle']);
$router->get('/jobs/edit/{id}/pipeline', 'JobController@editPipeline', ['AuthMiddleware::handle']);
$router->post('/jobs/edit/{id}/pipeline', 'JobController@updatePipeline', ['AuthMiddleware::handle']);
$router->get('/jobs/edit/{id}/custom-question', 'JobController@editQuestions', ['AuthMiddleware::handle']);
$router->post('/jobs/edit/{id}/custom-question', 'JobController@updateQuestions', ['AuthMiddleware::handle']);
$router->get('/jobs/edit/{id}/draft-message', 'JobController@editDraftMessage', ['AuthMiddleware::handle']);
$router->post('/jobs/edit/{id}/draft-message', 'JobController@updateDraftMessage', ['AuthMiddleware::handle']);

$router->get('/jobs/{id}/interviews', 'JobController@show', ['AuthMiddleware::handle']);
$router->get('/jobs/{jobId}/reports/{reportId}', 'ReportController@show', ['AuthMiddleware::handle']);
$router->get('/jobs/{jobId}/reports/{reportId}/export', 'ReportController@export', ['AuthMiddleware::handle']);
$router->post('/jobs/{jobId}/reports/{reportId}/decision', 'ReportController@decision', ['AuthMiddleware::handle']);
$router->post('/jobs/{jobId}/reports/{reportId}/chat', 'ReportController@chat', ['AuthMiddleware::handle']);

// Candidates / Interviews
$router->get('/candidates', 'CandidateController@index', ['AuthMiddleware::handle']);
$router->get('/interviews', 'CandidateController@index', ['AuthMiddleware::handle']);
$router->get('/candidates/create', 'CandidateController@create', ['AuthMiddleware::handle']);
$router->post('/candidates/create', 'CandidateController@store', ['AuthMiddleware::handle']);
$router->get('/applications/{id}/manage', 'CandidateController@edit', ['AuthMiddleware::handle']);
$router->post('/applications/{id}/manage', 'CandidateController@update', ['AuthMiddleware::handle']);
$router->post('/applications/{id}/archive', 'CandidateController@archive', ['AuthMiddleware::handle']);
$router->post('/applications/{id}/restore', 'CandidateController@restore', ['AuthMiddleware::handle']);
$router->post('/applications/{id}/delete', 'CandidateController@destroy', ['AuthMiddleware::handle']);
$router->post('/applications/{id}/move', 'CandidateController@moveJob', ['AuthMiddleware::handle']);
$router->post('/applications/{id}/reactivate', 'CandidateController@reactivate', ['AuthMiddleware::handle']);
$router->post('/candidates/bulk', 'CandidateController@bulkAction', ['AuthMiddleware::handle']);
$router->get('/jobs/{jobId}/compare', 'CompareController@show', ['AuthMiddleware::handle']);
$router->get('/join/{interviewId}', 'JoinController@show');
$router->get('/api/candidates/check-email', 'CandidateController@checkEmail', ['AuthMiddleware::handle']);
$router->get('/interviews/{id}/details', 'CandidateController@details', ['AuthMiddleware::handle']);
$router->post('/interviews/{id}/details', 'CandidateController@updateDetails', ['AuthMiddleware::handle']);
$router->post('/interviews/{id}/upload', 'CandidateController@uploadVideo', ['AuthMiddleware::handle']);
$router->get('/applications/{id}/resume', 'CandidateController@downloadResumeByApplication', ['AuthMiddleware::handle']);
$router->get('/candidates/{id}/resume', 'CandidateController@downloadResume', ['AuthMiddleware::handle']);

// Settings (master data)
$router->get('/settings', 'SettingsController@index', ['AuthMiddleware::handle']);
$router->post('/settings', 'SettingsController@store', ['AuthMiddleware::handle']);
$router->post('/settings/locations', 'SettingsController@storeLocation', ['AuthMiddleware::handle']);
$router->post('/settings/integrations', 'SettingsController@storeIntegrations', ['AuthMiddleware::handle']);
$router->post('/settings/{type}/{id}/delete', 'SettingsController@delete', ['AuthMiddleware::handle']);

// Pipeline
$router->get('/pipeline', 'PipelineController@index', ['AuthMiddleware::handle']);

// Leaderboard
$router->get('/leaderboard', 'LeaderboardController@index', ['AuthMiddleware::handle']);

// Team
$router->get('/team', 'TeamController@index', ['AuthMiddleware::handle']);
$router->get('/team/create', 'TeamController@create', ['AuthMiddleware::handle']);
$router->post('/team/create', 'TeamController@store', ['AuthMiddleware::handle']);
$router->get('/team/edit/{id}', 'TeamController@edit', ['AuthMiddleware::handle']);
$router->post('/team/edit/{id}', 'TeamController@update', ['AuthMiddleware::handle']);
$router->post('/team/delete/{id}', 'TeamController@delete', ['AuthMiddleware::handle']);

// Pending recordings (FRD)
$router->get('/pending-recordings', 'PendingRecordingController@index', ['AuthMiddleware::handle']);
$router->post('/pending-recordings/{id}/assign', 'PendingRecordingController@assign', ['AuthMiddleware::handle']);
$router->get('/api/pending/interviews', 'PendingRecordingController@searchInterviews', ['AuthMiddleware::handle']);

// API
$router->get('/api/master/departments', 'ApiController@departments', ['AuthMiddleware::handle']);
$router->get('/api/master/states', 'ApiController@states', ['AuthMiddleware::handle']);
$router->get('/api/master/cities', 'ApiController@cities', ['AuthMiddleware::handle']);
$router->get('/api/jobs/{id}/stages', 'ApiController@jobStages', ['AuthMiddleware::handle']);
$router->get('/api/video/{interviewId}', 'ApiController@videoSas', ['AuthMiddleware::handle']);
$router->post('/api/interviews/{interviewId}/integrity-event', 'ApiController@integrityEvent');

$router->dispatch();
