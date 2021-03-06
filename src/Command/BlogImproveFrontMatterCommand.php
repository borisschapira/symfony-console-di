<?php

namespace ConsoleDI\Command;

use KzykHys\FrontMatter\Document;
use KzykHys\FrontMatter\FrontMatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class BlogImproveFrontMatterCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('blog:improve-front-matter')
            ->addArgument('src', InputArgument::REQUIRED, 'The path to the root folder of contents')
            ->addArgument('dest', InputArgument::OPTIONAL, 'The path to the root folder of contents');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $src = $input->getArgument('src');
        $destination = $input->getArgument('dest');


        $re = '/(?P<lang>fr|en)\/(?P<section>citoyen|papa|web)\/(?P<year>[0-9]+)\/\k<year>-(?P<month>[0-9]+)-(?P<day>[0-9]+)-(?P<title>.*)\/\k<year>-\k<month>-\k<day>-\k<title>.md/';

        // For jekyll-locale branch
        // $re = '/(?P<section>web|dad|citizen)\/_posts\/(?P<year>[0-9]+)\/\k<year>-(?P<month>[0-9]+)-(?P<day>[0-9]+)-(?P<title>.*)\/\k<year>-\k<month>-\k<day>-\k<title>.md/';

        // For _locales only
        //$re = '/(?P<lang>fr|en|)\'.$re;

        $finder = new Finder();
        $finder->files()->in($src)->name('*.md');

        $index = 0;

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {

            // Dump the relative path to the file
            preg_match($re, $file->getRelativePathname(), $matches);

            //var_dump($matches);

            if (count($matches) > 10) {

                // Dump the relative path to the file, omitting the filename
                $output->writeln(++$index . '. Source file: ' . $file->getRealPath() . ' (' . $file->getBasename('.md') . ')');

                /** @var Document $document */
                $document = FrontMatter::parse(file_get_contents($file->getRealPath()));

                // $output->writeln($matches['section'] . ' ' . $matches['day'] .'/'.$matches['month'].'/'.$matches['year']. ' '. $matches['title']);

                $document['date'] = $matches['year'] . '-' . $matches['month'] . '-' . $matches['day'];
                // $document['publishDate'] = array_key_exists('publishDate', $document) ? $document['publishDate'] : $document['date'] ;
                $document['lang'] = $matches['lang'];
                $document['type'] = 'post';
                if ( $document['lang'] == 'fr' ) {
                    $document['locale'] = 'fr_FR';
                } else if ( $document['lang'] == 'en' ) {
                    $document['locale'] = 'en_US';
                }

                if ($destination != null) {
                    $document['slug'] = substr($file->getBasename('.md'), 11);
                    $document['section'] = $matches['section'];
                    $document[$matches['year']] = [$matches['month']];
                } else {
                    
                    if (!isset($document['categories'])) {
                        $document['categories'] = [$matches['section']];
                    }
                    $categories = $document['categories'];
                    //$categories[] = $matches['section'];
                    $document['categories'] = array_unique($categories);

                    // $output->writeln($matches['section']);

                    $document->offsetUnset('section');
                    //$document->offsetUnset('locale');
                }

                if ($destination == null) {
                    // $output->writeln('Updating file.');
                    $file_path = $file->getRealPath();
                    $new_file_path = str_replace( '/' . $matches['section'] . '/', '/' . $document['categories'][0] . '/', $file_path);

                    if(strlen($matches['title']) > 50 ) {

                        if(!isset($document['slug'])) {
                            $document['slug'] = $matches['title'];
                        } 

                        $new_file_path = str_replace($matches['title'],mb_substr($matches['title'],0,50),$new_file_path);
                    }

                    // if(isset($document['i18n-key'])) {
                    //     if(!isset($document['slug'])) {
                    //         $document['slug'] = $matches['title'];
                    //     }
                    //     $new_file_path = str_replace($matches['title'],$document['i18n-key'], $new_file_path);
                    //     $output->writeln($new_file_path);
                    // }

                    // if (strpos($new_file_path, '_posts/en/') !== false) {
                    //     $new_file_path = str_replace("_posts/en/","_locales/en/", $new_file_path);
                    //     $output->writeln($new_file_path);
                    // } else if (strpos($new_file_path, '_posts/fr/') !== false) {
                    //     $new_file_path = str_replace("_posts/fr/","_posts/", $new_file_path);
                    //     $output->writeln($new_file_path);
                    // }

                    if (count($document['categories']) == 1 && $document['categories'][0] == $matches['section']) {
                        $document->offsetUnset('categories');
                    }

                    if ($document['publishDate'] == $document['date']) {
                        $document->offsetUnset('publishDate');
                    }

                    $document->offsetUnset('lang');

                    if($new_file_path!=$file_path){

                        $output->writeln('Moving file.');

                        $new_folder_path = dirname($new_file_path);
                        if (!file_exists($new_folder_path)) {
                            $output->writeln('Creating folder: ' . $new_folder_path);
                            mkdir($new_folder_path, 0777, true);
                        }

                        unlink($file_path);
                    } else {
                        // $output->writeln('Updating file.');
                    }

                    file_put_contents($new_file_path, FrontMatter::dump($document));

                } else {
                    $destinationPathTemplate = $destination . '/content/' . $matches['section'] . '/' . $matches['year'] . '/' . $matches['month'] . '/' . substr($file->getBasename('.md'), 11) . '/';
                    $filesDestinationPathTemplate = $destination . '/static/files/' . $matches['year'] . '/' . $matches['month'] . '/' . substr($file->getBasename('.md'), 11) . '/';

                    $urlTemplate = ($matches['section'] == 'web' ? '' : ('/' . $matches['section'])) . '/' . $matches['year'] . '/' . $matches['month'] . '/' . substr($file->getBasename('.md'), 11) . '/';

                    $filename = isset($document["i18n-key"]) ? $document["i18n-key"] : 'index';
                    $output->writeln('Writing file to: ' . $destinationPathTemplate . $filename . '.' . $matches['lang'] . '.md');

                    if (!file_exists($destinationPathTemplate)) {
                        mkdir($destinationPathTemplate, 0777, true);
                    }

                    $dump = FrontMatter::dump($document);
                    $dump = str_replace('{{ page.url }}', '{{<fileFolder>}}', $dump);
                    $dump = str_replace('<!-- more -->', '<!--more-->', $dump);
                    file_put_contents($destinationPathTemplate . $filename . '.' . $matches['lang'] . '.md', $dump);

                    $insideFinder = new Finder();
                    $insideFinder->files()->in($file->getPath())->notName('*.md');

                    if (count($insideFinder)>0) {

                        if (!file_exists($filesDestinationPathTemplate)) {
                            mkdir($filesDestinationPathTemplate, 0777, true);
                        }

                        /** @var SplFileInfo $ressource */
                        foreach ($insideFinder as $ressource) {
                            $output->writeln('Additional ressource : ' . $ressource->getRealPath());
                            copy($ressource->getRealPath(), $filesDestinationPathTemplate . $ressource->getRelativePathname());
                            copy($ressource->getRealPath(), $destinationPathTemplate . $ressource->getRelativePathname());
                        }
                    }

                }
                // $output->writeln('');
            }
        }
    }
}
