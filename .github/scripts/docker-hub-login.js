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

// Logs in to Docker Hub unless the account has fewer pulls left than a push makes and than the runner's IP. Without a
// token, or if the login fails, pulls are anonymous.
const AUTH = 'https://auth.docker.io/token?service=registry.docker.io&scope=repository:ratelimitpreview/test:pull';
const MANIFEST = 'https://registry-1.docker.io/v2/ratelimitpreview/test/manifests/latest';
// The jobs of a push read the account's pulls at once. With an empty cache, Unit's 4 pull 2 images each, Integration's
// 8 pull 3, and Image, E2E and Lint 3 each.
const PUSH_PULLS = 41;

// A HEAD request does not count as a pull. Without a ratelimit-remaining header, there is no limit.
async function pullsLeft(authorization) {
    const auth = await fetch(AUTH, authorization ? { headers: { authorization } } : {});
    if (!auth.ok) {
        throw new Error(`auth.docker.io answered ${auth.status}`);
    }
    const { token } = await auth.json();
    const head = await fetch(MANIFEST, { method: 'HEAD', headers: { authorization: `Bearer ${token}` } });
    if (head.status === 429) {
        return 0;
    }
    if (!head.ok) {
        throw new Error(`registry-1.docker.io answered ${head.status}`);
    }
    const left = head.headers.get('ratelimit-remaining');
    return left === null ? Infinity : parseInt(left, 10);
}

module.exports = async ({ core, exec }) => {
    const { DOCKERHUB_TOKEN: token, DOCKERHUB_USERNAME: username } = process.env;
    if (!token) {
        return;
    }

    try {
        const basic = `Basic ${Buffer.from(`${username}:${token}`).toString('base64')}`;
        const [account, anonymous] = await Promise.all([pullsLeft(basic), pullsLeft()]);
        core.info(`Docker Hub pulls left: ${account} for ${username}, ${anonymous} for this runner's IP.`);
        if (account < PUSH_PULLS && anonymous > account) {
            core.info('Pulls are anonymous.');
            return;
        }
    } catch (error) {
        core.warning(`Docker Hub's pull limits could not be read: ${error.message}.`);
    }

    const status = await exec.exec('docker', ['login', '--username', username, '--password-stdin'], {
        input: Buffer.from(token),
        ignoreReturnCode: true,
    });
    if (status !== 0) {
        core.warning('The Docker Hub login failed; pulls are anonymous.');
    }
};
