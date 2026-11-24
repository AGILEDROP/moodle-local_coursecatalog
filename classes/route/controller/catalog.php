<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_coursecatalog\route\controller;

use core\param;
use core\router\route;
use core\router\schema\parameters\path_parameter;
use local_coursecatalog\catalog_page;
use Psr\Http\Message\ResponseInterface;

/**
 * Serve the catalog at its short URL.
 *
 * @package   local_coursecatalog
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalog {
    /**
     * Display a catalog page.
     *
     * @param ResponseInterface $response HTTP response.
     * @param string $slug Catalog page slug.
     * @return ResponseInterface
     */
    #[route(
        path: '/{slug}',
        method: 'GET',
        pathtypes: [
            new path_parameter(name: 'slug', type: param::ALPHANUMEXT),
        ],
    )]
    public function view(ResponseInterface $response, string $slug): ResponseInterface {
        $html = catalog_page::render($slug);
        if ($html === null) {
            $response = $response->withStatus(404);
            $html = catalog_page::render_not_found($slug);
        }
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
