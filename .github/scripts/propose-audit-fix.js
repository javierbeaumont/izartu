//  izartu: web bookmark manager based on tags
//
//  Copyright (C) 2011-2026 Javier Beaumont <javierbeaumont@users.noreply.github.com>
//
//  This file is part of izartu.
//
//  izartu is free software: you can redistribute it and/or modify
//  it under the terms of the GNU Affero General Public License as
//  published by the Free Software Foundation, either version 3 of the
//  License, or (at your option) any later version.
//
//  izartu is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
//  GNU Affero General Public License for more details.
//
//  You should have received a copy of the GNU Affero General Public License
//  along with izartu. If not, see <https://www.gnu.org/licenses/>.

// Proposes the updated composer.lock in a pull request.
module.exports = async ({ github, context }) => {
    const { owner, repo } = context.repo;
    const branch = 'dependency-audit-fix';
    const message = 'build: update the dependencies with known vulnerabilities';
    const content = require('node:fs').readFileSync('composer.lock', 'utf8');

    const { data: base } = await github.rest.git.getCommit({ owner, repo, commit_sha: context.sha });
    const { data: tree } = await github.rest.git.createTree({
        owner,
        repo,
        base_tree: base.tree.sha,
        tree: [{ path: 'composer.lock', mode: '100644', type: 'blob', content }],
    });
    if (tree.sha === base.tree.sha) {
        return;
    }
    const { data: commit } = await github.rest.git.createCommit({
        owner,
        repo,
        message,
        tree: tree.sha,
        parents: [context.sha],
    });

    // A token without the `workflows` permission may not move a branch over commits that change a workflow. Deleting
    // the branch closes its pull request.
    await github.rest.git.deleteRef({ owner, repo, ref: `heads/${branch}` }).catch((error) => {
        if (error.status !== 422) {
            throw error;
        }
    });
    await github.rest.git.createRef({ owner, repo, ref: `refs/heads/${branch}`, sha: commit.sha });
    await github.rest.pulls.create({ owner, repo, head: branch, base: 'main', title: message });

    // A pull request made with the workflow's token starts no workflow.
    for (const workflow_id of ['lint-and-static-analysis.yml', 'security-analysis.yml', 'tests.yml']) {
        await github.rest.actions.createWorkflowDispatch({ owner, repo, workflow_id, ref: branch });
    }
};
