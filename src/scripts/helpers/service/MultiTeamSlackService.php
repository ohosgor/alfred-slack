<?php
namespace AlfredSlack\Helpers\Service;

use AlfredSlack\Libs\Utils;

use AlfredSlack\Helpers\Core\CustomCommander;
use AlfredSlack\Helpers\Http\MultiCurlInteractor;

use Frlnc\Slack\Http\SlackResponseFactory;

class MultiTeamSlackService implements SlackServiceInterface {

    private $services = [];

	public function __construct () {
        $this->initServices();
	}

    private function initServices () {
        $teams = $this->getTeams();
        if ($teams !== false) {
        	foreach ($teams as $team) {
	        	$this->services[$team->team_id] = new SingleTeamSlackService($team->team_id);
	        }
	    } else {
            $oldToken = Utils::getWorkflows()->getPassword('token');
            Utils::getWorkflows()->delete('token');
            $this->addToken($oldToken);
            $this->refreshCache();
        }
    }

    public function setCacheLock ($lock) {
        if ($lock === true) {
            Utils::getWorkflows()->write('1', 'cache.lock');
        } else {
            Utils::getWorkflows()->delete('cache.lock');
        }
    }

    public function isCacheLocked () {
        return (Utils::getWorkflows()->read('cache.lock') === 1);
    }

    public function getTeams () {
    	return Utils::getWorkflows()->read('teams');
    }

    public function addTeam ($team) {
        $teams = Utils::getWorkflows()->read('teams');
        if ($teams === false) {
            $teams = [];
        }
        if (is_null(Utils::find($teams, [ 'team_id' => $team['team_id'] ]))) {
            $teams[] = [ 'team' => $team['team'], 'team_id' => $team['team_id'] ];
            Utils::getWorkflows()->write($teams, 'teams');
        }
    }

    public function addToken ($token) {
    	$interactor = new MultiCurlInteractor;
        $interactor->setResponseFactory(new SlackResponseFactory);
        $tempCommander = new CustomCommander($token, $interactor);
        $auth = $tempCommander->execute('auth.test')->getBody();

        if (Utils::getWorkflows()->setPassword('token.'.$auth['team_id'], $token)) {
            $this->addTeam($auth);
            // If safe password is set, remove the unsafe one
            Utils::getWorkflows()->delete('token.'.$auth['team_id']);
            $this->services[$auth['team_id']] = new SingleTeamSlackService($auth['team_id']);
        }
    }
	
	public function addTokenUnsafe ($token) {
        /*
        Utils::getWorkflows()->write($token, 'token');
        $this->initCommander();
        */
    }

    public function getProfileIcon ($userId) {
    	foreach ($this->services as $model) {
    		$icon = $model->getProfileIcon($userId);
    		if ($icon !== false) {
    			return $icon;
    		}
    	}
    	return false;
    }
    
    public function getFileIcon ($fileId) {
    	foreach ($this->services as $model) {
    		$icon = $model->getFileIcon($fileId);
    		if ($icon !== false) {
    			return $icon;
    		}
    	}
    	return false;
    }

    public function getChannels ($excludeArchived = false) {
    	$channels = [];
    	foreach ($this->services as $model) {
			$channels = array_merge($channels, $model->getChannels($excludeArchived));
    	}
    	return $channels;
    }

    public function getGroups ($excludeArchived = false) {
    	$groups = [];
    	foreach ($this->services as $model) {
			$groups = array_merge($groups, $model->getGroups($excludeArchived));
    	}
    	return $groups;
    }

    public function getIms ($excludeDeleted = false) {
    	$ims = [];
    	foreach ($this->services as $model) {
			$ims = array_merge($ims, $model->getIms($excludeDeleted));
    	}
    	return $ims;
    }

    public function openIm (\AlfredSlack\Models\UserModel $user) {
    	$teamId = $user->getAuth()->team_id;
        $model = $this->services[$teamId];
        return $user->openIm($user);
    }
    
    public function getUsers ($excludeDeleted = false) {
    	$users = [];
    	foreach ($this->services as $model) {
			$users = array_merge($users, $model->getUsers($excludeDeleted));
    	}
    	return $users;
    }

    public function getFiles () {
    	$files = [];
    	foreach ($this->services as $model) {
			$files = array_merge($files, $model->getFiles());
    	}
    	return $files;
    }

    public function getFile (\AlfredSlack\Models\FileModel $file) {
        $teamId = $file->getAuth()->team_id;
        $model = $this->services[$teamId];
        return $model->getFile($file);
    }

    public function getStarredItems () {
		$stars = [];
    	foreach ($this->services as $model) {
			$stars = array_merge($stars, $model->getStarredItems());
    	}
    	return $stars;
    }

    public function search ($query) {
        $res = [];
    	foreach ($this->services as $model) {
    		$search = $model->search($query);
            $res = $res + $search;
    	}
    	return $res;
    }

    public function getImByUser (\AlfredSlack\Models\UserModel $user) {
        $teamId = $user->getAuth()->team_id;
        $model = $this->services[$teamId];
        return $model->getImByUser($user);
    }

    public function setPresence ($isAway = false) {
    	foreach ($this->services as $model) {
    		$model->setPresence($isAway);
    	}
    }

    public function postMessage (\AlfredSlack\Models\ChatInterface $channel, $message, $asBot = false) {
        $teamId = $channel->getAuth()->getTeamId();
        $model = $this->services[$teamId];
        return $model->postMessage($channel, $message, $asBot);
    }

    public function getChannelHistory (\AlfredSlack\Models\ChannelModel $channel) {
        $teamId = $channel->getAuth()->getTeamId();
        $model = $this->services[$teamId];
    	return $model->getChannelHistory($channel);
    }

    public function getGroupHistory (\AlfredSlack\Models\GroupModel $group) {
        $teamId = $group->getAuth()->getTeamId();
        $model = $this->services[$teamId];
        return $model->getGroupHistory($group);
    }

    public function getImHistory (\AlfredSlack\Models\ImModel $im) {
        $teamId = $im->getAuth()->getTeamId();
        $model = $this->services[$teamId];
    	return $model->getImHistory($im);
    }

    public function refreshCache () {
        foreach ($this->services as $model) {
        	$model->refreshCache();
        }
    }

    public function markChannelAsRead (\AlfredSlack\Models\ChannelModel $channel) {
        $teamId = $channel->getAuth()->team_id;
    	$model = $this->services[$teamId];
    	return $model->markChannelAsRead($channel);
    }

	public function markGroupAsRead (\AlfredSlack\Models\GroupModel $group) {
        $teamId = $group->getAuth()->team_id;
    	$model = $this->services[$teamId];
    	return $model->markGroupAsRead($group);
	}

	public function markImAsRead (\AlfredSlack\Models\ImModel $im) {
        $teamId = $im->getAuth()->team_id;
		$model = $this->services[$teamId];
    	return $model->markImAsRead($im);
	}

	public function markAllAsRead () {
		foreach ($this->services as $model) {
    		$model->markAllAsRead();
    	}
	}

}