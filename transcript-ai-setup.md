Search in video
Your Automated Job Search & Application AI Agent [No-code with n8n, LinkedIn, Deepseek & Perplexity]
0:00
hey everyone in today's video I'm going
0:02
to walk you through how I use NN to
0:06
build an AI agent that handles my entire
0:10
job search and application process
0:13
completely on
0:15
autopilot we all know how tedious it is
0:18
um to scroll through the job boards and
0:21
manual apply to endless listings um not
0:24
to mention customizing um cou letters
0:27
for each uh single company
0:30
so imagine this with this AI agent you
0:34
will get the latest job postings sent
0:36
directly to you what even better is that
0:39
the AI agent will automatically send
0:42
your personalized cover letter and CV
0:44
straight to the company's xch child
0:47
email it's not bad right all right so
Demo of the AI Job Search Agent
0:51
let's dive into a quick Demo First here
0:54
is the LinkedIn job search link I put um
0:58
prompt engineer as the job post um and
1:02
then the location is the
1:04
US and right
1:07
here is the Google Sheets where the
1:10
latest job postings are automatically
1:12
stored based on the LinkedIn search um
1:16
so for the demo purpose uh let me remove
1:20
them
1:21
first all right
1:24
courts once I run this H workflow the
1:27
newest job posting will be updated
1:31
directly into the Google
1:35
[Music]
1:36
sheet
1:38
great the new job postings are already
1:41
updated directly here in the Google
1:46
Sheets okay
1:48
next let me try to ask the AI agent to
1:51
get all the job
1:58
records great is
2:02
done how about my personal
2:11
information like my experience my resume
2:15
my skill set
2:16
Etc and my content information as
2:22
well great it's
2:25
done basically start
2:28
here last but not
2:31
least and here is the exciting
2:34
part I'll tell the AI to send the
2:37
customize cover letter to all the
2:42
emails so for demo purpose um let me
2:45
just try to let me try to remove this
2:50
first and then change it to our own
2:54
email
2:57
address all right
3:05
and then let
3:13
me so let's ask the air agents to send
3:16
the cover letter now
3:20
send uh
3:23
cover leather to all the emails please
3:34
the cover letters have been successfully
3:37
sent to the respective companies proxer
3:40
and DFCU great let's check the
3:48
mailbox great it's sent
3:52
successfully talk name DF uh
3:55
DCU all right and then for another one
4:02
great it's successfully sent as
4:06
well it's all customized all customized
4:10
based on um the company's background and
4:14
uh using my own personal information my
4:17
own experience uh to customize the cover
4:20
L
4:21
great so
4:24
um and of course my resume is attached
4:27
here as well and this is just my resume
4:30
sample so with this AI
4:34
agent so with this AI agent your job
4:37
search and
4:39
applications can be run on autopilot all
4:42
you need is to focus on preparing your
4:44
interviews all right before we dive into
AI Agent Overview
4:47
the workflow details here in NN let's
4:50
talk about the tools we need first first
4:53
of all we need rssa it is for getting
4:56
the latest job post from linked job sech
5:00
and then it will be deep seek Ai and it
5:03
we will also need to use Sona Pro
5:06
powered by plexity AI to get the uh
5:10
contact details uh like the email of the
5:13
uh
5:14
company and there are main three
5:17
workflows here one two and
5:21
three um let's dive into the workflows
5:23
one by one first of all the first work
5:26
is to uh for auto job posting and
5:28
content extraction
5:30
first of all we uh get the uh job post
5:33
from an RSS feed and then convert job
5:36
titles send job titles to dipic API
5:39
generate a natural language Curry and
5:42
then will extract emails using
5:45
perplexity uh and filter duplicates so
5:48
if they uh um we just we want to have um
5:52
just new and unique emails to be
5:55
processed and stored to the Google
5:56
Sheets to avoid duplications and then we
6:00
store everything to the Google Sheets so
6:03
this is the first workflow I will dive
6:05
into this part um note by note later and
6:09
then for the second worklow is the AI
6:12
agent for the uh job
6:15
assistant so we automate the job
6:17
application process like um getting the
6:19
job data person personal information and
6:22
also sending the cover letters to each
6:25
of the company so we will generate
6:27
custom cover letters using uh the tool
6:30
uh using the AI agent as well and we'll
6:33
send um the emails as well so it's
6:36
triggered on chat um you can uh we can
6:39
change the trigger into other ways for
6:42
example like um schedule trigger for
6:45
like for time info or we can connect the
6:48
telegram trigger to that as
6:50
well okay so for the third workflow uh
6:54
with this one is for sending a cover
6:56
letter with
6:58
resume um so we start with the process
7:01
um with execute rle
7:04
trigger and then um map the record like
7:08
email subject message content and then
7:10
we download resume send the email with
7:13
map content uh we'll send an email with
7:16
the resume attached as well and then
7:18
return a success response after the
7:20
email is sent all right this uh three um
7:25
this is the high level overview of the
7:27
workflow let's dive into the det details
7:30
note by note okay so let's dive into the
AI Agent Workflow 1 - Auto LinkedIn Job Posting & Contact Extraction
7:33
workflow one by one here is the first
7:35
workflow so for this workflow we are
7:37
getting um the um job post the latest
7:40
job post from the LinkedIn search um
7:43
from the RSS and then we are going to um
7:47
get the contact details like the email
7:50
uh and also the company summary and
7:52
Company description from perity using
7:54
Sona Pro API and then um we'll save the
7:58
contacts and
8:00
uh and goate if the email
8:02
exists okay so for the first note uh it
8:06
is to get previous records we're using
8:09
Google
8:10
Sheets uh so basically for Google Sheets
8:13
um you can just um add Trump here Google
8:17
sheet the credentials O2 API continue
8:21
and then you sign in with Google and
8:23
it's very
8:25
easy and then for for this note after
8:28
you connect um your own Google sheet
8:31
account where for the resource we will
8:33
choose sheet um within document
8:37
operation we choose get rows for
8:40
documents we choose from list um
8:42
tutorial 9.3 um job post record so this
8:47
one is
8:48
it all
8:50
right and then the sheet name is
8:55
LinkedIn combine filters we choose and
8:59
so let's um actually let's click
9:04
Start all right we got the information
9:07
here and then we'll
9:10
aggregate so we can just search for this
9:12
note and then aggregate individual field
9:16
and then the input uh fi name we put um
9:20
company
9:21
emo all
9:23
right let's start and then for RSS rate
9:28
so we need to get this link how we going
9:30
to get this link uh first of all we are
9:32
going to visit our ss. app um you can
9:36
have 7 days free
9:39
trial and they have different plans uh
9:42
for the basic plan uh you have you can
9:45
have uh 15
9:48
fets and then up to uh 25 pts uh per
9:52
feet oh so that means you can have
9:58
um 15 \* 25 uh
10:01
275 uh posts uh within this free trial
10:05
period for the free plan uh we are not
10:07
able to use a social media uh so we may
10:11
not be able to add the LinkedIn job
10:13
search uh so that's why we need to um
10:16
use the uh free child version if you
10:18
like the solution you can subscribe to
10:20
the solutions okay so let's go back to
10:23
this uh RSS St app uh we can click uh
10:28
new feeds here
10:31
we can go to the job search here after
10:33
we get the LinkedIn job search link
10:37
copy and then we can just paste it here
10:39
and
10:42
generate choose uh RSS
10:46
generator so after you um set up your
10:49
feed you can save your feed
10:51
here and then you can copy this rs. app
10:55
link and that's where you can paste on
10:59
the UR
11:00
here so you can choose uh for this note
11:03
you can choose RSS read this
11:06
one so after you paste it and then uh we
11:10
can click test step and see how the
11:13
process is okay so we have already got
11:16
the uh latest job
11:19
py go back to the notes here and the
11:23
limit which uh we put it as uh 25 and we
11:26
keep the first items here
11:31
all right and then we convert to um Json
11:34
string and here we are going to use dips
11:37
uh AI um so we can use post method um
11:42
api. dip.com
11:44
chat completions authentication we
11:47
choose generic credential type and then
11:50
generic off type we choose header off um
11:53
and just choose from your head uh choose
11:56
DPS API uh from your head off
12:00
um let me quickly go through how we can
12:02
set up um the credentials for deep
12:13
seek again we can choose header off
12:18
here and then type
12:23
authorization and then we we can go to
12:25
dip
12:27
seek.com um API platform
12:30
form and the API Keys you can create
12:33
your own any API Keys
12:36
here all
12:38
right and bear in mind we need to add
12:42
barrier space before we paste the API
12:46
key that's how you can set up your um
12:50
deep seek API using the header o and
12:52
then we choose uh we enable send body
12:55
for the body content type we choose um
12:58
Json specified body we use using
13:01
Json and then we can just copy and paste
13:05
this for the message uh we are using um
13:10
this one uh remember the J uh Json
13:12
stringify the prompt you a Google search
13:15
expert uh please convert this job title
13:18
to uh natural language on curing the
13:21
following three items HR email of the
13:23
company the company email and the
13:25
company summary basically it's the
13:27
company background description and we
13:29
need the AI to return in Json format
13:33
natural language query string comedy
13:36
name string commy uh summary
13:39
string all right then we can click test
13:42
step to proceed
14:08
okay we got the results
14:10
here and then we'll convert it to
14:14
Json so for this code we can ask AI to
14:17
uh
14:18
generate uh for us convert to Jason and
14:23
then for the mode we choose run once for
14:26
all items and language we choose
14:27
JavaScript test step
14:34
so basically um we turn this content and
14:37
separate into uh natural language query
14:40
uh company name company
14:43
summary okay and then we will use a
14:48
complexity uh to process to get the
14:51
emails here so we use post method uh API
14:55
perplexity AI as well/ chat SL comption
15:00
authentication we use generic credential
15:02
type again is similar to the setting of
15:05
deep seek uh AI uh you use head off
15:09
capacity um all
15:13
right and then for body conent type we
15:16
choose Json um specifi body we use using
15:21
Json
15:23
here remember to Jon stringify as
15:26
well so we are going to string ify the
15:29
content
15:31
here all
15:33
right this one this is the natural
15:36
language Curry we would like per to
15:39
search so basically what is the HR email
15:41
for the specific Company the company
15:44
name and a summary of the company so
15:47
this is the uh research uh we would like
15:51
the perplexity perplexity to to do for
15:53
us and then for model we use Sona
15:57
Pro all right can click test
16:07
step all right uh it's completed we can
16:11
proceed to the next stage to retrieve
16:13
the email
16:15
address again we can um use as AI this
16:18
feature to write this or you can just
16:20
copy and paste this as
16:22
well um run once for all items
16:25
JavaScript as
16:27
well and for for this note we add the if
16:30
note here if email
16:33
exist because um we just want to add um
16:36
those job listings with email that that
16:41
are found uh from complexity uh we we
16:45
only input those um job listing into our
16:48
Google
16:49
Sheets all right so it is not equal to
16:53
any then we will proceed to the next
16:56
stage and then for this filter we are
16:59
trying to filter out to make sure that
17:01
no
17:03
duplications are made on the goet so we
17:05
will not input a duplicated um email um
17:10
uh to the go
17:12
sheets so last but not least we're going
17:15
to um paste to input all the um emails
17:21
we found on
17:23
capacity into our Google
17:26
Sheets great this is our first workflow
AI Agent Workflow 2 - AI Job Application Assistant
17:29
after we complete the first workflow
17:31
let's talk about the second workflow so
17:33
for this workflow um we are going to
17:35
make the um air agent for um doing the
17:38
job assistant work um so for the trigger
17:42
we use when chat message receiv received
17:45
um you can use um other trigger um for
17:48
example uh schedule interval or telegram
17:52
trigger as
17:53
well okay so this is note and for this a
17:58
agent we can just search agent here and
18:01
then you can just import this and we can
18:04
rename it uh so we rename it into job
18:07
assistant
18:09
agent uh the type of agent we choose is
18:11
tool agent tools agent and prom we take
18:14
from previous note
18:17
automatically and for the system message
18:19
we input this you are a helpful job uh
18:23
application assistant you will use the
18:24
following tools to perform specific
18:27
tasks um so basically we have um three
18:30
tools so let me show you um get job
18:33
records which is uh getting this school
18:35
sheet that we have and then personal
18:38
information personal information is
18:40
where we store our information for
18:42
example my address contact number kind
18:45
of email my professional summary
18:47
basically it's my CV
18:50
yeah and then um the send email
18:55
Tool uh we use this to send email so I
18:58
will go through through this Tool uh
19:01
later all right so let's continue with
19:04
the prompt
19:06
here um to get job record from the
19:08
Google Sheets basically I just listed
19:10
The Columns
19:11
here in the Google Sheets and then send
19:14
email I I put please use the following
19:17
cover letter template here is a cover
19:19
letter format example and please
19:22
customiz each cover letter based on the
19:23
comedy description and my own experience
19:26
and skill set can be found in personal
19:29
information tool which is the Google
19:30
Document I input
19:32
here and first uh this is a
19:35
template and I put in uh date here so I
19:39
this is the format I want so you can
19:41
just copy and paste this and put it in
19:43
inside the system prompt here for the
19:46
cover letter template I just basically
19:48
copy from uh indeed um one of the
19:53
largest uh largest drop search platform
19:56
um so you can change the style that you
19:59
want um this is just um the template I
20:03
chose all right and then for the last
20:06
two uh which is to uh the personal
20:09
information just get my personal
20:11
information like my email contact
20:13
numbers experience uh
20:15
Etc all right so this is the settings
20:17
for this
20:19
note and the open a chat model uh I
20:23
choose uh gbd 40 you can pick your
20:26
preferred model as well you can choose
20:27
dip seek as
20:29
well and I put a window buffer memory
20:33
here uh but for this St we don't need
20:36
this so I just deactivated
20:38
it for the get job
20:41
record let's dive into this again we
20:44
need to connect with the Google
20:46
Sheets and then uh tool description we
20:49
set manually read all job records it
20:52
contains the
20:53
following which is the the column title
20:56
the column header I put here
20:59
uh resource uh sheet uh within document
21:02
operation which choose get rols document
21:04
from list basically this is the Google
21:07
sheet um I refer to and this is the
21:09
sheet I am referring to from this note
21:13
and for filters I choose com combined
21:15
filters I choose
21:17
and okay for the um personal um
21:21
information um I would like to um talk a
21:24
little bit about the Google uh doc uh
21:28
credentials connections so we need to
21:30
create the CR credentials here um so
21:34
first of all we need to go to um the uh
21:39
console Cloud uh Google and then search
21:41
for Google Docs
21:42
API and then we can create new
21:45
credentials here all off client
21:48
ID choose uh web application um name it
21:52
in the way that you want for the um tool
21:55
and then um after you name it you create
21:57
it
21:59
and then after you create this um you
22:01
will have the client ID and the Cent c
22:03
key and then you can just copy and uh
22:06
copy this uh redirect
22:09
URL and paste it here and that and that
22:14
will be uh set and you can just save it
22:16
and then um just sign in with your
22:19
Google account again so that's how you
22:21
can set it um with the Google uh doc
22:24
credential and then for the two
22:26
description we set manually uh
22:29
description we retrieve my personal
22:30
information like name address and
22:32
content number Etc resource we choose
22:35
document and operation we chuse get and
22:38
this is your document ID so basically go
22:41
to Google uh
22:43
sheets uh I mean Google doc uh you you
22:46
can just choose this uh as your copy
22:50
this um uh chain and just past it here
22:54
as document
22:56
ID all right
22:59
and then for the last two which is uh to
23:01
send email to send a cover letter to the
23:04
emails listed
23:06
here so for the name is uh sendor email
23:10
uh description we call this tool to send
23:12
email the input should be email email
23:14
title and email HTML uh message source
23:18
which choose um uh
23:21
database and workflow it choose resume s
23:25
message basically this is the workflow
23:27
I'll talk about this later
23:29
fill to respond uh fill to return
23:32
response and we have the specified input
23:36
schema um schema type generate from
23:38
Jason example um first of all
23:42
email title HTML message all
23:48
right so basically this is the setting
23:51
um for um the second
AI Agent Workflow 3 - Send Cover Letter with Resume
23:55
workflow great um I've talk about the
23:58
second second workflow the job assistant
24:00
agent um let's talk about the send email
24:03
Tool uh which is here uh
24:07
okay this
24:12
[Music]
24:14
one all
24:18
right so we can just focus on the upper
24:21
part this one is it uh basically these
24:25
uh just uh we just have five notes for
24:27
this workflow uh execute workflow
24:29
trigger so basically this workflow will
24:31
just trigger it when um this tool is
24:35
called by this
24:36
agent so we have
24:39
this and then we can just search for uh
24:42
execute workflow
24:46
trigger um and then we we need to add
24:49
this at a
24:51
fil and then we we name it as map
24:55
records for mode we choose menu mapping
24:59
uh the F set email and then we can just
25:03
track and drop this track and drop like
25:06
this and title TR and drop this htmm
25:10
message we try and drop this as well all
25:13
right and it's set for the Google drive
25:17
again you can connect with your own uh
25:18
Google
25:19
Drive and um for the file for the uh
25:23
resource you choose file operation which
25:25
is download and um file which is from
25:29
list which is your uh own um
25:33
resume for um the Google Drive API again
25:38
basically it is the same process that uh
25:41
we went through the Google Doc it's just
25:44
that we need to choose the Google Drive
25:46
API instead of Google doc
25:48
API all
25:51
right the fourth step will be the Gmail
25:54
to send message so we can search the
25:57
Gmail note
26:00
uh this is the account and then for the
26:02
resource we choose message operation we
26:05
choose send and then two um which is the
26:09
uh email subject which is the title
26:11
email type HTML which is map um the HL
26:15
message shown in the previous notes
26:18
basically which just like this three not
26:21
uh three
26:23
items all right and then uh we can
26:26
choose um add option here for this
26:30
append an attribution it means that we
26:33
don't want to have a signature uh like
26:37
having the NN this email is sent through
26:40
NN so we can disable this for the
26:43
attachment we choose um the attachment
26:46
um field name uh
26:48
data all
26:51
right and then for the last uh note we
26:54
choose um the return here so again we
26:59
can just add edit fields and then we
27:03
name it as return so we use uh menu
27:06
mapping as a mode and um for the field
27:09
set we use response string s sets great
27:14
so for the third workflow is completed
27:16
as well so basically this is the setup
27:20
for the AI agent that can help you to
27:23
find job send cou letters and get
27:25
interviews easily or on autopilot
27:28
um so hope this helps um and free to let
27:31
me know U what you think about this uh
27:34
in the comment section below see you in
27:36
our next video
